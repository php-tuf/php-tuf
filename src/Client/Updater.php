<?php

namespace Tuf\Client;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\StreamInterface;
use Tuf\Client\DurableStorage\DurableStorageAccessValidator;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\FormatException;
use Tuf\Exception\MetadataException;
use Tuf\Exception\NotFoundException;
use Tuf\Exception\PotentialAttackException\DenialOfServiceAttackException;
use Tuf\Exception\PotentialAttackException\FreezeAttackException;
use Tuf\Exception\PotentialAttackException\InvalidHashException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\JsonNormalizer;
use Tuf\KeyDB;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\RoleDB;

/**
 * Class Updater
 *
 * @package Tuf\Client
 */
class Updater
{

    const MAX_ROOT_DOWNLOADS = 1024;

    /**
     * The maximum number of bytes to download if the remote file size is not
     * known.
     */
    const MAXIMUM_DOWNLOAD_BYTES = 100000;

    /**
     * @var \array[][]
     */
    protected $mirrors;

    /**
     * The permanent storage (e.g., filesystem storage) for the client metadata.
     *
     * @var \ArrayAccess
     */
    protected $durableStorage;

    /**
     * The role database for the repository.
     *
     * @var \Tuf\RoleDB
     */
    protected $roleDB;

    /**
     * The key database for the repository.
     *
     * @var \Tuf\KeyDB
     */
    protected $keyDB;

    /**
     * The repo file fetcher.
     *
     * @var \Tuf\Client\RepoFileFetcherInterface
     */
    protected $repoFileFetcher;

    /**
     * Whether the repo has been refreshed or not.
     *
     * @see ::download()
     * @see ::refresh()
     *
     * @var bool
     */
    protected $isRefreshed = false;

    /**
     * The time after which metadata should be considered expired.
     *
     *   @return \DateTimeImmutable
     */
    private $metadataExpiration;

    /**
     * Updater constructor.
     *
     * @param \Tuf\Client\RepoFileFetcherInterface $repoFileFetcher
     *     The repo fetcher.
     * @param mixed[][] $mirrors
     *     A nested array of mirrors to use for fetching signing data from the
     *     repository. Each child array contains information about the mirror:
     *     - url_prefix: (string) The URL for the mirror.
     *     - metadata_path: (string) The path within the repository for signing
     *       metadata.
     *     - targets_path: (string) The path within the repository for targets
     *       (the actual update data that has been signed).
     *     - confined_target_dirs: (array) @todo What is this for?
     *       https://github.com/php-tuf/php-tuf/issues/161
     * @param \ArrayAccess $durableStorage
     *     An implementation of \ArrayAccess that stores its contents durably,
     *     as in to disk or a database. Values written for a given repository
     *     should be exposed to future instantiations of the Updater that
     *     interact with the same repository.
     *
     *
     */
    public function __construct(RepoFileFetcherInterface $repoFileFetcher, array $mirrors, \ArrayAccess $durableStorage)
    {
        $this->repoFileFetcher = $repoFileFetcher;
        $this->mirrors = $mirrors;
        $this->durableStorage = new DurableStorageAccessValidator($durableStorage);
    }

    /**
     * Gets the type for the file name.
     *
     * @param string $fileName
     *   The file name.
     *
     * @return string
     *   The type.
     */
    private static function getFileNameType(string $fileName): string
    {
        $parts = explode('.', $fileName);
        array_pop($parts);
        return array_pop($parts);
    }

    /**
     * @todo Add docs. See python comments:
     *     https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
     *     https://github.com/php-tuf/php-tuf/issues/162
     * @todo The Python implementation has an optional flag to "unsafely update
     *     root if necessary". Do we need it?
     *     https://github.com/php-tuf/php-tuf/issues/21
     *
     * @param bool $force
     *   (optional) If false, return early if this updater has already been
     *   refreshed. Defaults to false.
     *
     * @return boolean
     *     TRUE if the data was successfully refreshed.
     *
     * @see https://github.com/php-tuf/php-tuf/issues/21
     *
     * @throws \Tuf\Exception\MetadataException
     *   Throw if an upated root metadata file is not valid.
     * @throws \Tuf\Exception\PotentialAttackException\FreezeAttackException
     *   Throw if a freeze attack is detected.
     * @throws \Tuf\Exception\PotentialAttackException\RollbackAttackException
     *   Throw if a rollback attack is detected.
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if the signature threshold has not be reached.
     */
    public function refresh(bool $force = false): bool
    {
        if ($force) {
            $this->isRefreshed = false;
            $this->metadataExpiration = null;
        }
        if ($this->isRefreshed) {
            return true;
        }

        // *TUF-SPEC-v1.0.13 Section 5.0
        $this->metadataExpiration = $this->createExpirationDate();

        // *TUF-SPEC-v1.0.13 Section 5.1
        $rootData = RootMetadata::createFromJson($this->durableStorage['root.json']);
        $rootData->setIsTrusted(true);

        $this->roleDB = RoleDB::createFromRootMetadata($rootData);
        $this->keyDB = KeyDB::createFromRootMetadata($rootData);

        // *TUF-SPEC-v1.0.13 Section 5.2
        $this->updateRoot($rootData);

        // *TUF-SPEC-v1.0.13 Section 5.3
        $newTimestampContents = $this->fetchFile('timestamp.json');
        $newTimestampData = TimestampMetadata::createFromJson($newTimestampContents);
        // *TUF-SPEC-v1.0.13 Section 5.3.1
        $this->checkSignatures($newTimestampData);

        // If the timestamp or snapshot keys were rotating then the timestamp file
        // will not exist.
        if (isset($this->durableStorage['timestamp.json'])) {
            // *TUF-SPEC-v1.0.13 Section 5.3.2.1 and 5.3.2.2
            $currentStateTimestampData = TimestampMetadata::createFromJson($this->durableStorage['timestamp.json']);
            static::checkRollbackAttack($currentStateTimestampData, $newTimestampData);
        }

        // *TUF-SPEC-v1.0.13 Section 5.3.3
        static::checkFreezeAttack($newTimestampData, $this->metadataExpiration);
        // TUF-SPEC-v1.0.13 Section 5.3.4: Persist timestamp metadata
        $this->durableStorage['timestamp.json'] = $newTimestampContents;
        $newTimestampData->setIsTrusted(true);

        $snapshotInfo = $newTimestampData->getFileMetaInfo('snapshot.json');
        $snapShotVersion = $snapshotInfo['version'];

        // TUF-SPEC-v1.0.13 Section 5.4
        if ($rootData->supportsConsistentSnapshots()) {
            $newSnapshotContents = $this->fetchFile("$snapShotVersion.snapshot.json");
            // TUF-SPEC-v1.0.13 Section 5.4.1
            $newSnapshotData = SnapshotMetadata::createFromJson($newSnapshotContents);
            $newTimestampData->verifyNewHashes($newSnapshotData);
        } else {
            // @todo Add support for not using consistent snapshots in
            //    https://github.com/php-tuf/php-tuf/issues/97
            throw new \UnexpectedValueException("Currently only repos using consistent snapshots are supported.");
        }

        // TUF-SPEC-v1.0.13 Section 5.4.2
        $this->checkSignatures($newSnapshotData);

        // TUF-SPEC-v1.0.13 Section 5.4.3
        $newTimestampData->verifyNewVersion($newSnapshotData);

        if (isset($this->durableStorage['snapshot.json'])) {
            $currentSnapShotData = SnapshotMetadata::createFromJson($this->durableStorage['snapshot.json']);
            // TUF-SPEC-v1.0.13 Section 5.4.4
            static::checkRollbackAttack($currentSnapShotData, $newSnapshotData);
        }

        // TUF-SPEC-v1.0.13 Section 5.4.5
        static::checkFreezeAttack($newSnapshotData, $this->metadataExpiration);

        // TUF-SPEC-v1.0.13 Section 5.4.6
        $this->durableStorage['snapshot.json'] = $newSnapshotContents;
        $newSnapshotData->setIsTrusted(true);

        // TUF-SPEC-v1.0.13 Section 5.5
        if ($rootData->supportsConsistentSnapshots()) {
            $this->fetchAndVerifyTargetsMetadata('targets');
        } else {
            // @todo Add support for not using consistent snapshots in
            //    https://github.com/php-tuf/php-tuf/issues/97
            throw new \UnexpectedValueException("Currently only repos using consistent snapshots are supported.");
        }
        $this->isRefreshed = true;
        return true;
    }

    /**
     * Converts a metadata timestamp string into an immutable DateTime object.
     *
     * @param string $timestamp
     *     The timestamp string in the metadata.
     *
     * @return \DateTimeImmutable
     *     An immutable DateTime object for the given timestamp.
     *
     * @throws FormatException
     *     Thrown if the timestamp string format is not valid.
     */
    protected static function metadataTimestampToDateTime(string $timestamp): \DateTimeImmutable
    {
        $dateTime = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", $timestamp);
        if ($dateTime === false) {
            throw new FormatException($timestamp, "Could not be interpreted as a DateTime");
        }
        return $dateTime;
    }

    /**
     * Checks for a rollback attack.
     *
     * Verifies that an incoming remote version of a metadata file is greater
     * than or equal to the last known version.
     *
     * @param \Tuf\Metadata\MetadataBase $localMetadata
     *     The locally stored metadata from the most recent update.
     * @param \Tuf\Metadata\MetadataBase $remoteMetadata
     *     The latest metadata fetched from the remote repository.
     * @param integer|null $expectedRemoteVersion
     *     If not null this is expected version of remote metadata.
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\RollbackAttackException
     *     Thrown if a potential rollback attack is detected.
     */
    protected static function checkRollbackAttack(MetadataBase $localMetadata, MetadataBase $remoteMetadata, int $expectedRemoteVersion = null): void
    {
        if ($localMetadata->getType() !== $remoteMetadata->getType()) {
            throw new \UnexpectedValueException('\Tuf\Client\Updater::checkRollbackAttack() can only be used to compare metadata files of the same type. '
               . "Local is {$localMetadata->getType()} and remote is {$remoteMetadata->getType()}.");
        }
        $type = $localMetadata->getType();
        $remoteVersion = $remoteMetadata->getVersion();
        if ($expectedRemoteVersion && ($remoteVersion !== $expectedRemoteVersion)) {
            throw new RollbackAttackException("Remote $type metadata version \"$$remoteVersion\" " .
              "does not the expected version \"$$expectedRemoteVersion\"");
        }
        $localVersion = $localMetadata->getVersion();
        if ($remoteVersion < $localVersion) {
            $message = "Remote $type metadata version \"$$remoteVersion\" " .
                "is less than previously seen $type version \"$$localVersion\"";
            throw new RollbackAttackException($message);
        }
        if ($type === 'timestamp' || $type === 'snapshot') {
            $localMetaFileInfos = $localMetadata->getSigned()['meta'];
            foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
                /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $remoteMetadata */
                if ($remoteFileInfo = $remoteMetadata->getFileMetaInfo($fileName, true)) {
                    if ($remoteFileInfo['version'] < $localFileInfo['version']) {
                        $message = "Remote $type metadata file '$fileName' version \"${$remoteFileInfo['version']}\" " .
                          "is less than previously seen  version \"${$localFileInfo['version']}\"";
                        throw new RollbackAttackException($message);
                    }
                } elseif ($type === 'snapshot' && static::getFileNameType($fileName) === 'targets') {
                    // TUF-SPEC-v1.0.13 Section 5.4.4
                    // Any targets metadata filename that was listed in the trusted snapshot metadata file, if any, MUST
                    // continue to be listed in the new snapshot metadata file.
                    throw new RollbackAttackException("Remote snapshot metadata file references '$fileName' but this is not present in the remote file");
                }
            }
        }
    }

    /**
     * Checks for a freeze attack.
     *
     * Verifies that metadata has not expired, and assumes a potential freeze
     * attack if it has.
     *
     * @param \Tuf\Metadata\MetadataBase $metadata
     *     The metadata for the timestamp role.
     * @param \DateTimeInterface $now
     *     The current date and time at runtime.
     *
     * @return void
     *
     * @throws FreezeAttackException
     *     Thrown if a potential freeze attack is detected.
     */
    protected static function checkFreezeAttack(MetadataBase $metadata, \DateTimeInterface $now): void
    {
        $metadataExpiration = static::metadataTimestampToDatetime($metadata->getExpires());
        if ($metadataExpiration < $now) {
            $format = "Remote %s metadata expired on %s";
            throw new FreezeAttackException(sprintf($format, $metadata->getRole(), $metadataExpiration->format('c')));
        }
    }

    /**
     * Checks signatures on a verifiable structure.
     *
     * @param \Tuf\Metadata\MetadataBase $metadata
     *     The metadata to check signatures on.
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if the signature threshold has not be reached.
     */
    protected function checkSignatures(MetadataBase $metadata): void
    {
        $signatures = $metadata->getSignatures();

        $role = $this->roleDB->getRole($metadata->getRole());
        $needVerified = $role->getThreshold();
        $verifiedKeySignatures = [];

        $canonicalBytes = JsonNormalizer::asNormalizedJson($metadata->getSigned());
        foreach ($signatures as $signature) {
            // Don't allow the same key to be counted twice.
            if ($role->isKeyIdAcceptable($signature['keyid']) && $this->verifySingleSignature($canonicalBytes, $signature)) {
                $verifiedKeySignatures[$signature['keyid']] = true;
            }
            // @todo Determine if we should check all signatures and warn for
            //     bad signatures even if this method returns TRUE because the
            //     threshold has been met.
            //     https://github.com/php-tuf/php-tuf/issues/172
            if (count($verifiedKeySignatures) >= $needVerified) {
                break;
            }
        }

        if (count($verifiedKeySignatures) < $needVerified) {
            throw new SignatureThresholdExpception("Signature threshold not met on " . $metadata->getRole());
        }
    }

    /**
     * @param string $bytes
     *     The canonical JSON string of the 'signed' section of the given file.
     * @param \ArrayAccess $signatureMeta
     *     The ArrayAccess object of metadata for the signature. Each signature
     *     metadata contains two elements:
     *     - keyid: The identifier of the key signing the role data.
     *     - sig: The hex-encoded signature of the canonical form of the
     *       metadata for the role.
     *
     * @return boolean
     *     TRUE if the signature is valid for the.
     */
    protected function verifySingleSignature(string $bytes, \ArrayAccess $signatureMeta): bool
    {
        // Get the pubkey from the key database.
        $pubkey = $this->keyDB->getKey($signatureMeta['keyid'])->getPublic();

        // Encode the pubkey and signature, and check that the signature is
        // valid for the given data and pubkey.
        $pubkeyBytes = hex2bin($pubkey);
        $sigBytes = hex2bin($signatureMeta['sig']);
        // @todo Check that the key type in $signatureMeta is ed25519; return
        //     false if not.
        //     https://github.com/php-tuf/php-tuf/issues/168
        return \sodium_crypto_sign_verify_detached($sigBytes, $bytes, $pubkeyBytes);
    }


    /**
     * Updates the root metadata if needed.
     *
     * @param \Tuf\Metadata\RootMetadata $rootData
     *   The current root metadata.
     *
     * @throws \Tuf\Exception\MetadataException
     *   Throw if an upated root metadata file is not valid.
     * @throws \Tuf\Exception\PotentialAttackException\FreezeAttackException
     *   Throw if a freeze attack is detected.
     * @throws \Tuf\Exception\PotentialAttackException\RollbackAttackException
     *   Throw if a rollback attack is detected.
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if an updated root file is not signed with the need signatures.
     *
     * @return void
     */
    private function updateRoot(RootMetadata &$rootData): void
    {
        $rootsDownloaded = 0;
        $originalRootData = $rootData;
        // *TUF-SPEC-v1.0.13 Section 5.2.2
        $nextVersion = $rootData->getVersion() + 1;
        while ($nextRootContents = $this->repoFileFetcher->fetchMetadataIfExists("$nextVersion.root.json", static::MAXIMUM_DOWNLOAD_BYTES)) {
            $rootsDownloaded++;
            if ($rootsDownloaded > static::MAX_ROOT_DOWNLOADS) {
                throw new DenialOfServiceAttackException("The maximum number root files have already been downloaded: " . static::MAX_ROOT_DOWNLOADS);
            }
            $nextRoot = RootMetadata::createFromJson($nextRootContents);
            // *TUF-SPEC-v1.0.13 Section 5.2.3
            $this->checkSignatures($nextRoot);
            // Update Role and Key databases to use the new root information.
            $this->roleDB = RoleDB::createFromRootMetadata($nextRoot, true);
            $this->keyDB = KeyDB::createFromRootMetadata($nextRoot, true);
            $this->checkSignatures($nextRoot);
            // *TUF-SPEC-v1.0.13 Section 5.2.4
            static::checkRollbackAttack($rootData, $nextRoot, $nextVersion);
            $nextRoot->setIsTrusted(true);
            $rootData = $nextRoot;
            // *TUF-SPEC-v1.0.13 Section 5.2.5 - Needs no action.
            // Note that the expiration of the new (intermediate) root metadata
            // file does not matter yet, because we will check for it in step
            // 1.8.

            // *TUF-SPEC-v1.0.13 Section 5.2.6 and 5.2.7
            $this->durableStorage['root.json'] = $nextRootContents;
            $nextVersion = $rootData->getVersion() + 1;
            // *TUF-SPEC-v1.0.13 Section 5.2.8 Repeat the above steps.
        }
        // *TUF-SPEC-v1.0.13 Section 5.2.9
        static::checkFreezeAttack($rootData, $this->metadataExpiration);

        // *TUF-SPEC-v1.0.13 Section 5.2.10: Delete the trusted timestamp and snapshot files if either
        // file has rooted keys.
        if ($rootsDownloaded &&
           (static::hasRotatedKeys($originalRootData, $rootData, 'timestamp')
           || static::hasRotatedKeys($originalRootData, $rootData, 'snapshot'))) {
            unset($this->durableStorage['timestamp.json'], $this->durableStorage['snapshot.json']);
        }
    }

    /**
     * Determines if the new root metadata has rotated keys for a role.
     *
     * @param \Tuf\Metadata\RootMetadata $previousRootData
     *   The previous root metadata.
     * @param \Tuf\Metadata\RootMetadata $newRootData
     *   The new root metadta.
     * @param string $role
     *   The role to check for rotated keys.
     *
     * @return boolean
     *   True if the keys for the role have been rotated, otherwise false.
     */
    private static function hasRotatedKeys(RootMetadata $previousRootData, RootMetadata $newRootData, string $role): bool
    {
        $previousRole = $previousRootData->getRoles()[$role] ?? null;
        $newRole = $newRootData->getRoles()[$role] ?? null;
        return $previousRole !== $newRole;
    }

    /**
     * Synchronously fetches a file from the remote repo.
     *
     * @param string $fileName
     *   The name of the file to fetch.
     * @param integer $maxBytes
     *   (optional) The maximum number of bytes to download.
     *
     * @return string
     *   The contents of the fetched file.
     */
    private function fetchFile(string $fileName, int $maxBytes = self::MAXIMUM_DOWNLOAD_BYTES): string
    {
        return $this->repoFileFetcher->fetchMetadata($fileName, $maxBytes)
            ->then(function (StreamInterface $data) use ($fileName, $maxBytes) {
                $this->checkLength($data, $maxBytes, $fileName);
                return $data;
            })
            ->wait();
    }

    /**
     * Verifies the length of a data stream.
     *
     * @param \Psr\Http\Message\StreamInterface $data
     *   The data stream to check.
     * @param int $maxBytes
     *   The maximum acceptable length of the stream, in bytes.
     * @param string $fileName
     *   The filename associated with the stream.
     *
     * @throws \Tuf\Exception\DownloadSizeException
     *   If the stream's length exceeds $maxBytes in size.
     */
    protected function checkLength(StreamInterface $data, int $maxBytes, string $fileName): void
    {
        $error = new DownloadSizeException("$fileName exceeded $maxBytes bytes");
        $size = $data->getSize();

        if (isset($size)) {
            if ($size > $maxBytes) {
                throw $error;
            }
        } else {
            // @todo Handle non-seekable streams.
            // https://github.com/php-tuf/php-tuf/issues/169
            $data->rewind();
            $data->read($maxBytes);

            // If we reached the end of the stream, we didn't exceed the
            // maximum number of bytes.
            if ($data->eof() === false) {
                throw $error;
            }
            $data->rewind();
        }
    }

    /**
     * Verifies a stream of data against a known TUF target.
     *
     * @param string $target
     *   The path of the target file. Needs to be known to the most recent
     *   targets metadata downloaded in ::refresh().
     * @param \Psr\Http\Message\StreamInterface $data
     *   A stream pointing to the downloaded target data.
     *
     * @throws \Tuf\Exception\MetadataException
     *   If the target has no trusted hash(es).
     * @throws \Tuf\Exception\PotentialAttackException\InvalidHashException
     *   If the data stream does not match the known hash(es) for the target.
     */
    protected function verify(string $target, StreamInterface $data): void
    {
        $this->refresh();

        $targetsMetadata = $this->getMetadataForTarget($target);
        if ($targetsMetadata === null) {
            throw new NotFoundException($target, 'Target');
        }
        $maxBytes = $targetsMetadata->getLength($target) ?? static::MAXIMUM_DOWNLOAD_BYTES;
        $this->checkLength($data, $maxBytes, $target);

        $hashes = $targetsMetadata->getHashes($target);
        if (count($hashes) === 0) {
            throw new MetadataException("No trusted hashes are available for '$target'");
        }
        foreach ($hashes as $algo => $hash) {
            // If the stream has a URI that refers to a file, use
            // hash_file() to verify it. Otherwise, read the entire stream
            // as a string and use hash() to verify it.
            $uri = $data->getMetadata('uri');
            if ($uri && file_exists($uri)) {
                $streamHash = hash_file($algo, $uri);
            } else {
                $streamHash = hash($algo, $data->getContents());
                $data->rewind();
            }

            if ($hash !== $streamHash) {
                throw new InvalidHashException($data, "Invalid $algo hash for $target");
            }
        }
    }

    /**
     * Downloads a target file, verifies it, and returns its contents.
     *
     * @param string $target
     *   The path of the target file. Needs to be known to the most recent
     *   targets metadata downloaded in ::refresh().
     * @param mixed ...$extra
     *   Additional arguments to pass to the file fetcher.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *   A promise representing the eventual verified result of the download
     *   operation.
     */
    public function download(string $target, ...$extra): PromiseInterface
    {
        $this->refresh();

        $targetsMetadata = $this->getMetadataForTarget($target);
        if ($targetsMetadata === null) {
            return new RejectedPromise(new NotFoundException($target, 'Target'));
        }

        // If the target isn't known, immediately return a rejected promise.
        try {
            $length = $targetsMetadata->getLength($target) ?? static::MAXIMUM_DOWNLOAD_BYTES;
        } catch (NotFoundException $e) {
            return new RejectedPromise($e);
        }

        return $this->repoFileFetcher->fetchTarget($target, $length, ...$extra)
            ->then(function (StreamInterface $stream) use ($target) {
                $this->verify($target, $stream);
                return $stream;
            });
    }

    /**
     * Gets a target metadata object that contains the specified target.
     *
     * @param string $target
     *   The path of the target file. Needs to be known to the most recent
     *   targets metadata downloaded in ::refresh().
     * @param \Tuf\Metadata\TargetsMetadata|null $targetsMetadata
     *   The targets metadata to search or null. If null then the search will
     *   start at the top level 'targets.json' file.
     * @param string[] $searchedRoles
     *   The roles that have already been searched. This is for internal use only and should not be passed by calling code.
     *   calls to this function and should be provided by any callers.
     *
     * @return \Tuf\Metadata\TargetsMetadata|null
     *   The target metadata with a match for the target, or null no match is
     *   found.
     */
    protected function getMetadataForTarget(string $target, ?TargetsMetadata $targetsMetadata = null, array $searchedRoles = []): ?TargetsMetadata
    {
        if ($targetsMetadata === null) {
            if (!empty($searchedRoles)) {
                throw new \UnexpectedValueException('$searchedRoles should never be provided by outside calls to \Tuf\Client\Updater::getMetadataForTarget(). It is only used for recursive calls.');
            }
            // If no target metadata is provided then start searching with the top level targets.json file.
            /** @var \Tuf\Metadata\TargetsMetadata $targetsMetadata */
            $targetsMetadata = TargetsMetadata::createFromJson($this->durableStorage['targets.json']);
            if ($targetsMetadata->hasTarget($target)) {
                return $targetsMetadata;
            }
        }

        $delegatedKeys = $targetsMetadata->getDelegatedKeys();
        foreach ($delegatedKeys as $keyId => $delegatedKey) {
            $this->keyDB->addKey($keyId, $delegatedKey);
        }
        foreach ($targetsMetadata->getDelegatedRoles() as $delegatedRole) {
            $delegatedRoleName = $delegatedRole->getName();
            if (in_array($delegatedRoleName, $searchedRoles, true)) {
                // TUF-SPEC-v1.0.13 Section 5.5.6.1
                // If this role has been visited before, then skip this role (so that cycles in the delegation graph are avoided).
                continue;
            }
            if (!$this->roleDB->roleExists($delegatedRoleName)) {
                $this->roleDB->addRole($delegatedRole);
            }
            if (!$delegatedRole->matchesPath($target)) {
                // Targets must match the path in all roles in the delegation chain so if the path does not match
                // do not evaluate this role or any roles it delegates to.
                continue;
            }

            $this->fetchAndVerifyTargetsMetadata($delegatedRoleName);
            /** @var \Tuf\Metadata\TargetsMetadata $newTargetsData */
            $newTargetsData = TargetsMetadata::createFromJson($this->durableStorage["$delegatedRoleName.json"]);
            if ($newTargetsData->hasTarget($target)) {
                return $newTargetsData;
            }
            if ($delegatedRole->isTerminating()) {
                // If the role is terminating then we do not search this targets metadata for additional delegations.
                continue;
            }
            if ($matchingTargetMetadata = $this->getMetadataForTarget($target, $newTargetsData, $searchedRoles)) {
                return $matchingTargetMetadata;
            }
        }
        return null;
    }

    /**
     * Fetches and verifies a targets metadata file.
     *
     * The metadata file will be stored as '$role.json'.
     *
     * @param string $role
     *   The role name. This may be 'targets' or a delegated role.
     */
    private function fetchAndVerifyTargetsMetadata(string $role): void
    {
        $newSnapshotData = SnapshotMetadata::createFromJson($this->durableStorage['snapshot.json']);
        $newSnapshotData->setIsTrusted(true);
        $targetsVersion = $newSnapshotData->getFileMetaInfo("$role.json")['version'];
        $newTargetsContent = $this->fetchFile("$targetsVersion.$role.json");
        $newTargetsData = TargetsMetadata::createFromJson($newTargetsContent, $role);
        // TUF-SPEC-v1.0.13 Section 5.5.1
        $newSnapshotData->verifyNewHashes($newTargetsData);
        // TUF-SPEC-v1.0.13 Section 5.5.2
        $this->checkSignatures($newTargetsData);
        // TUF-SPEC-v1.0.13 Section 5.5.3
        $newSnapshotData->verifyNewVersion($newTargetsData);
        // TUF-SPEC-v1.0.13 Section 5.5.4
        static::checkFreezeAttack($newTargetsData, $this->metadataExpiration);
        $newTargetsData->setIsTrusted(true);
        // TUF-SPEC-v1.0.13 Section 5.5.5
        $this->durableStorage["$role.json"] = $newTargetsContent;
    }

    /**
     * Creates the expiration date after which metadata will be consider expired.
     *
     * @return \DateTimeImmutable
     *   The expiration date.
     */
    protected function createExpirationDate(): \DateTimeImmutable
    {
        $fakeNow = '2020-01-01T00:00:00Z';
        return static::metadataTimestampToDateTime($fakeNow);
    }
}

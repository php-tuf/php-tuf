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
    private static function getFileNameType(string $fileName)
    {
        $parts = explode('.', $fileName);
        array_pop($parts);
        return array_pop($parts);
    }

    /**
     * @todo Add docs. See python comments:
     *     https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
     * @todo The Python implementation has an optional flag to "unsafely update
     *     root if necessary". Do we need it?
     *
     * @param bool $force
     *   (optional) If false, return early if this updater has already been
     *   refreshed. Defaults to false.
     *
     * @return boolean
     *     TRUE if the data was successfully refreshed.
     *
     * @see https://github.com/php-tuf/php-tuf/issues/21
     */
    public function refresh(bool $force = false) : bool
    {
        if ($force) {
            $this->isRefreshed = false;
        }
        if ($this->isRefreshed) {
            return true;
        }

        $rootData = RootMetadata::createFromJson($this->durableStorage['root.json']);
        $rootData->setIsTrusted(true);

        $this->roleDB = RoleDB::createFromRootMetadata($rootData);
        $this->keyDB = KeyDB::createFromRootMetadata($rootData);

        $this->updateRoot($rootData);

        $nowDate = $this->getCurrentTime();

        // TUF-SPEC-v1.0.9 Section 5.1.11. Will be used in spec step 5.4.3.
        //$consistent = $rootData['consistent'];

        // *TUF-SPEC-v1.0.9 Section 5.2
        $newTimestampContents = $this->fetchFile('timestamp.json');
        $newTimestampData = TimestampMetadata::createFromJson($newTimestampContents);
        // *TUF-SPEC-v1.0.9 Section 5.2.1
        $this->checkSignatures($newTimestampData);

        // If the timestamp or snapshot keys were rotating then the timestamp file
        // will not exist.
        if (isset($this->durableStorage['timestamp.json'])) {
            // *TUF-SPEC-v1.0.9 Section 5.2.2.1 and 5.2.2.2
            $currentStateTimestampData = TimestampMetadata::createFromJson($this->durableStorage['timestamp.json']);
            static::checkRollbackAttack($currentStateTimestampData, $newTimestampData);
        }

        // *TUF-SPEC-v1.0.9 Section 5.2.3
        static::checkFreezeAttack($newTimestampData, $nowDate);
        // TUF-SPEC-v1.0.9 Section 5.2.4: Persist timestamp metadata
        $this->durableStorage['timestamp.json'] = $newTimestampContents;
        $newTimestampData->setIsTrusted(true);

        $snapshotInfo = $newTimestampData->getFileMetaInfo('snapshot.json');
        $snapShotVersion = $snapshotInfo['version'];

        // TUF-SPEC-v1.0.9 Section 5.3
        if ($rootData->supportsConsistentSnapshots()) {
            $newSnapshotContents = $this->fetchFile("$snapShotVersion.snapshot.json");
            // TUF-SPEC-v1.0.9 Section 5.3.1
            $newSnapshotData = SnapshotMetadata::createFromJson($newSnapshotContents);
            $newTimestampData->verifyNewMetaData($newSnapshotData);
        } else {
            throw new \UnexpectedValueException("Currently only repos using consistent snapshots are supported.");
        }

        // TUF-SPEC-v1.0.9 Section 5.3.2
        $this->checkSignatures($newSnapshotData);

        if (isset($this->durableStorage['snapshot.json'])) {
            $currentSnapShotData = SnapshotMetadata::createFromJson($this->durableStorage['snapshot.json']);
            // TUF-SPEC-v1.0.9 Section 5.3.3
            static::checkRollbackAttack($currentSnapShotData, $newSnapshotData);
        }

        // TUF-SPEC-v1.0.9 Section 5.3.4
        static::checkFreezeAttack($newSnapshotData, $nowDate);

        // TUF-SPEC-v1.0.9 Section 5.3.5
        $this->durableStorage['snapshot.json'] = $newSnapshotContents;
        $newSnapshotData->setIsTrusted(true);

        // TUF-SPEC-v1.0.9 Section 5.4
        if ($rootData->supportsConsistentSnapshots()) {
            $this->fetchAndVerifyTargetsMetadata('targets');
        } else {
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
    protected static function metadataTimestampToDateTime(string $timestamp) : \DateTimeImmutable
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
    protected static function checkRollbackAttack(MetadataBase $localMetadata, MetadataBase $remoteMetadata, int $expectedRemoteVersion = null) : void
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
                    // TUF-SPEC-v1.0.9 Section 5.3.3
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
    protected static function checkFreezeAttack(MetadataBase $metadata, \DateTimeInterface $now) :void
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
     * @param \Tuf\Metadata\MetadataBase $metaData
     *     The metadata to check signatures on.
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if the signature thresold has not be reached.
     */
    protected function checkSignatures(MetadataBase $metaData) : void
    {
        $signatures = $metaData->getSignatures();

        $roleInfo = $this->roleDB->getRoleInfo($metaData->getRole());
        $needVerified = $roleInfo['threshold'];
        $haveVerified = 0;

        $canonicalBytes = JsonNormalizer::asNormalizedJson($metaData->getSigned());
        foreach ($signatures as $signature) {
            if ($this->isKeyIdAcceptableForRole($signature['keyid'], $metaData->getRole())) {
                $haveVerified += (int) $this->verifySingleSignature($canonicalBytes, $signature);
            }
            // @todo Determine if we should check all signatures and warn for
            //     bad signatures even this method returns TRUE because the
            //     threshold has been met.
            if ($haveVerified >= $needVerified) {
                break;
            }
        }

        if ($haveVerified < $needVerified) {
            throw new SignatureThresholdExpception("Signature threshold not met on " . $metaData->getRole());
        }
    }

    /**
     * Checks whether the given key is authorized for the role.
     *
     * @param string $keyId
     *     The key ID to check.
     * @param string $roleName
     *     The role name to check (e.g. 'root', 'snapshot', etc.).
     *
     * @return boolean
     *     TRUE if the key is authorized for the given role, or FALSE
     *     otherwise.
     */
    protected function isKeyIdAcceptableForRole(string $keyId, string $roleName) : bool
    {
        $roleKeyIds = $this->roleDB->getRoleKeyIds($roleName);
        return in_array($keyId, $roleKeyIds);
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
    protected function verifySingleSignature(string $bytes, \ArrayAccess $signatureMeta)
    {
        // Get the pubkey from the key database.
        $keyMeta = $this->keyDB->getKey($signatureMeta['keyid']);
        $pubkey = $keyMeta['keyval']['public'];

        // Encode the pubkey and signature, and check that the signature is
        // valid for the given data and pubkey.
        $pubkeyBytes = hex2bin($pubkey);
        $sigBytes = hex2bin($signatureMeta['sig']);
        // @todo Check that the key type in $signatureMeta is ed25519; return
        //     false if not.
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
    private function updateRoot(RootMetadata &$rootData)
    {
        $rootsDownloaded = 0;
        $originalRootData = $rootData;
        // *TUF-SPEC-v1.0.9 Section 5.1.2
        $nextVersion = $rootData->getVersion() + 1;
        while ($nextRootContents = $this->repoFileFetcher->fetchMetaDataIfExists("$nextVersion.root.json", static::MAXIMUM_DOWNLOAD_BYTES)) {
            $rootsDownloaded++;
            if ($rootsDownloaded > static::MAX_ROOT_DOWNLOADS) {
                throw new DenialOfServiceAttackException("The maximum number root files have already been downloaded: " . static::MAX_ROOT_DOWNLOADS);
            }
            $nextRoot = RootMetadata::createFromJson($nextRootContents);
            // *TUF-SPEC-v1.0.9 Section 5.1.3
            $this->checkSignatures($nextRoot);
            // Update Role and Key databases to use the new root information.
            $this->roleDB = RoleDB::createFromRootMetadata($nextRoot, true);
            $this->keyDB = KeyDB::createFromRootMetadata($nextRoot, true);
            $this->checkSignatures($nextRoot);
            // *TUF-SPEC-v1.0.9 Section 5.1.4
            static::checkRollbackAttack($rootData, $nextRoot, $nextVersion);
            $nextRoot->setIsTrusted(true);
            $rootData = $nextRoot;
            // *TUF-SPEC-v1.0.9 Section 5.1.5 - Needs no action.
            // Note that the expiration of the new (intermediate) root metadata
            // file does not matter yet, because we will check for it in step
            // 1.8.

            // *TUF-SPEC-v1.0.9 Section 5.1.6 and 5.1.7
            $this->durableStorage['root.json'] = $nextRootContents;
            $nextVersion = $rootData->getVersion() + 1;
            // *TUF-SPEC-v1.0.9 Section 5.1.8 Repeat the above steps.
        }
        // *TUF-SPEC-v1.0.9 Section 5.1.9
        static::checkFreezeAttack($rootData, $this->getCurrentTime());

        // *TUF-SPEC-v1.0.9 Section 5.1.10: Delete the trusted timestamp and snapshot files if either
        // file has rooted keys.
        if ($rootsDownloaded &&
           (static::hasRotatedKeys($originalRootData, $rootData, 'timestamp')
           || static::hasRotatedKeys($originalRootData, $rootData, 'snapshot'))) {
            unset($this->durableStorage['timestamp.json'], $this->durableStorage['snapshot.json']);
        }
    }

    /**
     * Gets the current time.
     *
     * @return \DateTimeImmutable
     *    The current time.
     * @throws \Tuf\Exception\FormatException
     *    Thrown if time format is not valid.
     */
    private function getCurrentTime(): \DateTimeImmutable
    {
        $fakeNow = '2020-01-01T00:00:00Z';
        $nowDate = static::metadataTimestampToDateTime($fakeNow);
        return $nowDate;
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
    private static function hasRotatedKeys(RootMetadata $previousRootData, RootMetadata $newRootData, string $role)
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
        return $this->repoFileFetcher->fetchMetaData($fileName, $maxBytes)
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
    private function checkLength(StreamInterface $data, int $maxBytes, string $fileName): void
    {
        $error = new DownloadSizeException("$fileName exceeded $maxBytes bytes");
        $size = $data->getSize();

        if (isset($size)) {
            if ($size > $maxBytes) {
                throw $error;
            }
        } else {
            // @todo Handle non-seekable streams.
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
     * @param \Tuf\Metadata\TargetsMetadata $targetsMetaData
     *   The targets metadata containing the information for $target.
     * @param \Psr\Http\Message\StreamInterface $data
     *   A stream pointing to the downloaded target data.
     *
     * @throws \Tuf\Exception\MetadataException
     *   If the target has no trusted hash(es).
     * @throws \Tuf\Exception\PotentialAttackException\InvalidHashException
     *   If the data stream does not match the known hash(es) for the target.
     */
    public function verify(string $target, TargetsMetadata $targetsMetaData, StreamInterface $data): void
    {
        $this->refresh();

        $maxBytes = $targetsMetaData->getLength($target) ?? static::MAXIMUM_DOWNLOAD_BYTES;
        $this->checkLength($data, $maxBytes, $target);

        $hashes = $targetsMetaData->getHashes($target);
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

        $targetsMetaData = $this->getMetadataForTarget($target, TargetsMetadata::createFromJson($this->durableStorage['targets.json']));
        if ($targetsMetaData === null) {
            return new RejectedPromise(new NotFoundException($target, 'Target'));
        }


        // If the target isn't known, immediately return a rejected promise.
        try {
            $length = $targetsMetaData->getLength($target) ?? static::MAXIMUM_DOWNLOAD_BYTES;
        } catch (NotFoundException $e) {
            return new RejectedPromise($e);
        }

        return $this->repoFileFetcher->fetchTarget($target, $length, ...$extra)
            ->then(function (StreamInterface $stream) use ($target, $targetsMetaData) {
                $this->verify($target, $targetsMetaData, $stream);
                return $stream;
            });
    }

    /**
     * @param string $target
     *   The path of the target file. Needs to be known to the most recent
     *   targets metadata downloaded in ::refresh().
     * @param \Tuf\Metadata\TargetsMetadata $targetsMetadata
     *   The targets metadata to search.
     *
     * @return \Tuf\Metadata\TargetsMetadata|null
     *   The target metadata with a match for the target, or null no match is
     *   found.
     */
    private function getMetadataForTarget(string $target, TargetsMetadata $targetsMetadata = null): ?TargetsMetadata
    {
        static $searchedRoles = [];
        static $snapshotMetadata = null;
        if ($targetsMetadata->getRole() === 'targets') {
            // Currently searching the top level 'targets.json' file.
            $searchedRoles = [];
            $snapshotMetadata = SnapshotMetadata::createFromJson($this->durableStorage['snapshot.json']);
            // Set the metadata as trusted because we retrieved from storage.
            $snapshotMetadata->setIsTrusted(true);
        } elseif ($snapshotMetadata === null || $searchedRoles) {
            // If $snapshotMetadata has not been set this method was called first with a targets metadata file besides
            // the top level targets.json.
            throw new \RuntimeException('\Tuf\Client\Updater::getMetadataForTarget should always be call first with top level targets.json file');
        }
        if ($targetsMetadata->hasTarget($target)) {
            // If a matching targets metadata has been found return it.
            // Reset the static variables in this function so that each top level call will start from scratch.
            $snapshotMetadata = null;
            $searchedRoles = [];
            return $targetsMetadata;
        } else {
            $delegatedKeys = $targetsMetadata->getDelegatedKeys();
            foreach ($delegatedKeys as $delegatedKey) {
                $this->keyDB->addKey($delegatedKey);
            }
            $delegatedRoles = $targetsMetadata->getDelegatedRoles();
            foreach ($delegatedRoles as $delegatedRole) {
                $delegatedRoleName = $delegatedRole['name'];
                if (in_array($delegatedRole, $searchedRoles)) {
                    // TUF-SPEC-v1.0.9 Section 5.4.5.1
                    // If this role has been visited before, then skip this role (so that cycles in the delegation graph are avoided).
                    continue;
                }
                if (!$this->roleDB->roleExists($delegatedRoleName)) {
                    $this->roleDB->addRole($delegatedRoleName, $delegatedRole);
                }
                $this->fetchAndVerifyTargetsMetadata($delegatedRoleName);
                $newTargetsData = TargetsMetadata::createFromJson($this->durableStorage["$delegatedRoleName.json"]);
                if ($matchingTargetMetadata = $this->getMetadataForTarget($target, $newTargetsData)) {
                    return $matchingTargetMetadata;
                }
            }
            return null;
        }
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
        // TUF-SPEC-v1.0.9 Section 5.4.1
        $newSnapshotData->verifyNewMetaData($newTargetsData);
        // TUF-SPEC-v1.0.9 Section 5.4.2
        $this->checkSignatures($newTargetsData);
        // TUF-SPEC-v1.0.9 Section 5.4.3
        static::checkFreezeAttack($newTargetsData, $this->getCurrentTime());
        $newTargetsData->setIsTrusted(true);
        // TUF-SPEC-v1.0.9 Section 5.4.4
        $this->durableStorage["$role.json"] = $newTargetsContent;
    }
}

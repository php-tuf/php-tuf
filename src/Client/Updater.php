<?php


namespace Tuf\Client;

use Tuf\Client\DurableStorage\FilesystemDurableStorage;
use Tuf\Client\DurableStorage\ValidatingArrayAccessAdapter;
use Tuf\Exception\RepositoryLayoutException;
use Tuf\KeyDB;
use Tuf\RepositoryDBCollection;
use Tuf\RoleDB;
use Tuf\JsonNormalizer;

/**
 * Class Updater
 *
 * @package Tuf\Client
 */
class Updater
{

  /**
   * @var string
   */
    protected $repoName;

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
     * List of hash algorithms that may be used for byte stream verification.
     *
     * @var \array
     */
    private $supportedHashAlgos;

    /**
     * Indicates whether the remote repository is using the TUF specification's consistent snapshots.
     *
     * @var bool
     */
    protected $consistentSnapshots;

  /**
   * Updater constructor.
   *
   * @param string $repositoryName
   *     A name the application assigns to the repository used by this Updater.
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
   *     An implementation of \ArrayAccess that stores its contents durably, as
   *     in to disk or a database. Values written for a given repository should
   *     be exposed to future instantiations of the Updater that interact with
   *     the same repository.
   */
    public function __construct(string $repositoryName, array $mirrors, \ArrayAccess $durableStorage)
    {
        $this->repoName = $repositoryName;
        $this->mirrors = $mirrors;
        $this->durableStorage = new ValidatingArrayAccessAdapter($durableStorage);
        $this->consistentSnapshots = false;
        $this->supportedHashAlgos = $this->buildSupportedHashAlgosList();
    }

  /**
   * @todo Update from python comment https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
   *
   * @todo The Python implementation has an optional flag to "unsafely update
   *     root if necessary". Do we need it?
   * @see https://github.com/php-tuf/php-tuf/issues/21
   */
    public function refresh()
    {
    }

  /**
   * Validates a target.
   *
   * @param $targetRepoPath
   * @param $targetStream
   *
   * @return bool
   *   Returns true if the target validates.
   */
    public function validateTarget($targetRepoPath, $targetStream)
    {
        $rootData = json_decode($this->durableStorage['root.json'], true);
        $signed = $rootData['signed'];

        $roleDB = RoleDB::createRoleDBFromRootMetadata($signed);
        $keyDB = KeyDB::createKeyDBFromRootMetadata($signed);
        // @TODO investigate whether we in fact need multiple simultaneous repository support.
        RepositoryDBCollection::singleton()->setDatabasesForRepository($keyDB, $roleDB, 'default');

        // SPEC: 1.1.
        $version = (int) $signed['version'];


        // SPEC: 1.2.
        $nextVersion = $version + 1;
        $nextRootContents = $this->getRepoFile("$nextVersion.root.json");
        if ($nextRootContents) {
          // @todo 🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥Add steps do root rotation spec steps 1.3 -> 1.7.
          //  Not production ready🙀.
            throw new \Exception("Root rotation not implemented.");
        }

        // SPEC: 1.8.
        $expires = $signed['expires'];
        $fakeNow = '2020-08-04T02:58:56Z';
        $expireDate = \DateTime::createFromFormat("Y-m-dTH:i:sZ", $fakeNow);
        $nowDate = \DateTime::createFromFormat("Y-m-dTH:i:sZ", $expires);
        if ($nowDate > $expireDate) {
            throw new \Exception("Root has expired. Potential freeze attack!");
          // @todo "On the next update cycle, begin at step 0 and version N of the
          //   root metadata file."
        }

        // @todo Implement spec 1.9. Does this step rely on root rotation?

        // SPEC: 1.10. Will be used in spec step 4.3.
        $this->consistentSnapshots = (bool)$signed['consistent_snapshot'];

        // SPEC: 2
        $timestampContents = $this->getRepoFile('timestamp.json');
        $timestampStructure = json_decode($timestampContents, true);
        // SPEC: 2.1
        if (! $this->checkSignatures($timestampStructure, 'timestamp')) {
            // @TODO Convert to a new subclass of PossibleAttackException once it's merged in. See PR#18.
            throw new \Exception("Improperly signed repository timestamp.");
        }

        // @TODO finish implementing SPEC 2.x. See PR#18.

        $timestamp = $timestampStructure['signed'];
        // SPEC: 3.0, 3.1
        $snapshotStructure = $this->updateMetadataIfChanged('snapshot', $timestamp);
        // SPEC: 3.2
        if (! $this->checkSignatures($snapshotStructure, 'snapshot')) {
            // @TODO Convert to a new subclass of PossibleAttackException once it's merged in. See PR#18.
            throw new \Exception('Improperly signed repository snapshot.');
        }

        return true;
    }

    protected function checkSignatures($verifiableStructure, $type)
    {
        $signatures = $verifiableStructure['signatures'];
        $signed = $verifiableStructure['signed'];

        list($roleDb, $keyDb) = RepositoryDBCollection::singleton()->getDatabasesForRepository();
        $roleInfo = $roleDb->getRoleInfo($type);
        $needVerified = $roleInfo['threshold'];
        $haveVerified = 0;

        $canonicalBytes = JsonNormalizer::asNormalizedJson($signed);
        foreach ($signatures as $signature) {
            if ($this->isKeyIdAcceptableForRole($signature['keyid'], $type)) {
                $haveVerified += (int)$this->verifySingleSignature($canonicalBytes, $signature);
            }
            // @TODO Determine if we should check all signatures and warn for bad
            //  signatures even this method returns TRUE because the threshold
            //  has been met.
            if ($haveVerified >= $needVerified) {
                break;
            }
        }

        return $haveVerified >= $needVerified;
    }

    protected function isKeyIdAcceptableForRole($keyId, $role)
    {
        list($roleDb, $keyDb) = RepositoryDBCollection::singleton()->getDatabasesForRepository();
        $roleKeyIds = $roleDb->getRoleKeyIds($role);
        return in_array($keyId, $roleKeyIds);
    }

    protected function verifySingleSignature($bytes, $signatureMeta)
    {
        list($roleDb, $keyDb) = RepositoryDBCollection::singleton()->getDatabasesForRepository();
        $keyMeta = $keyDb->getKey($signatureMeta['keyid']);
        $pubkey = $keyMeta['keyval']['public'];
        $pubkeyBytes = hex2bin($pubkey);
        $sigBytes = hex2bin($signatureMeta['sig']);
        // @TODO check that the key type in $signatureMeta is ed25519; return false if not.
        return \sodium_crypto_sign_verify_detached($sigBytes, $bytes, $pubkeyBytes);
    }

    /**
     * Verifies that the provided bytes match a provided hash.
     *
     * @param string $bytes
     *   The bytes to verify.
     * @param array $hashMeta
     *   An associative array of algorithms and associated hashes.
     * @return bool
     *   True if the bytes could be verified using any hash algorithm, false if the hash did not match.
     * @throws \Exception
     *   Thrown if none of the provided hash algorithms are supported by the php installation.
     */
    protected function verifyHash(string $bytes, array $hashMeta) : bool
    {
        foreach ($hashMeta as $algo => $hash) {
            $algo = strtolower($algo);
            if (in_array($algo, $this->supportedHashAlgos)) {
                if (! is_string($hash) || empty($hash)) {
                    throw new RepositoryLayoutException("Invalid representation of $algo hash.");
                }
                return hash($algo, $bytes) === $hash;
            }
        }
        // @TODO Convert to TufException once it's merged in. See PR#18.
        throw new \Exception("No hash algorithms common to local system and remote repository.");
    }

    /**
     * Updates metadata for the given role only if the local copy is not fresh
     * according to the referencing metadata.
     *
     * For example, metadata for the timestamp role references the current
     * snapshot metadata version. If local snapshot metadata already matches
     * the version found in the fresh timestamp metadata, then the snapshot
     * metadata is not refreshed.
     *
     * @param string $roleToUpdate
     *   A role name to update.
     *   Examples: "snapshot", "targets"
     * @param array $freshReferencingMetadata
     *   The signed portion of a referencing metadata.
     *   The provided metadata MUST be known to be fresh (such as by having
     *   been just retrieved) and MUST reference the role to update (plus
     *   encoding extension, such as .json) in its "meta" key.
     * @return array
     *   The metadata for the $roleToUpdate, whether or not it was updated.
     * @throws \Tuf\Exception\RepositoryLayoutException
     *   Throws if referencing metadata does not refer to the role to update.
     */
    protected function updateMetadataIfChanged(string $roleToUpdate, array $freshReferencingMetadata) : array
    {
        $metadataFilename = $this->roleToFilename($roleToUpdate);
        $expectedVersionInfo = $freshReferencingMetadata['meta'][$metadataFilename] ?? null;
        if ($expectedVersionInfo === null) {
            $message = "Role ${roleToUpdate} was not referenced in the provided metadata.";
            throw new RepositoryLayoutException($message);
        }

        $role = $this->getLocalMetadataIfCurrent($roleToUpdate, $expectedVersionInfo);
        if ($role !== null) {
            // Reference shows the local metadata is current.
            // Before continuing, ensure it is not expired.
            $this->ensureNotExpired($role);
            return $role;
        }

        // Get the metadata remotely.
        // If consistent snapshots are in use, the remote filename is a function of the current
        // snapshot metadata, unless the role being updated is snapshots in which case it is a
        // function of the current timestamp data.
        $remoteFilename = $this->roleToFilename($roleToUpdate);
        if ($this->consistentSnapshots) {
            $version = 0;
            switch ($roleToUpdate) {
                case 'snapshot':
                    $version = $expectedVersionInfo['version'];
                    break;
                default:
                    // All other roles will need to load snapshot from storage and use the version listed there.
                    throw new \Exception('Not implemented.');
            }
            if ((int)$version == 0) {
                $format = 'The TUF repository is using consistent snapshots, but the current version for \"%s\"'
                    . 'could not be determined.';
                throw new RepositoryLayoutException(sprintf($format, $roleToUpdate));
            }
            $remoteFilename = "${version}.$remoteFilename";
        }
        $rawRepoData = $this->getRepoFile($remoteFilename);
        if ($roleToUpdate === 'snapshot') {
            // For snapshot roles only, there may be a hash in the timestamp data that the snapshot should match.
            // SPEC: 3.1
            $expectedShasFromTimestamp = $expectedVersionInfo['hashes'] ?? [];
            // Per specification, "the new snapshot metadata file MUST match the hashes (if any)"
            if (count($expectedShasFromTimestamp) > 0) {
                if (!$this->verifyHash($rawRepoData, $expectedShasFromTimestamp)) {
                    // @TODO Convert to a (new?) subclass of PossibleAttackException once it's merged in. See PR#18.
                    $message = 'Snapshot hash in remote repository timestamp metadata does not match latest snapshot.';
                    throw new \Exception($message);
                }
            }
        }
        // @TODO Do something less quick & dirty to get useful types from remote repo. See issue #xx
        $repoData = json_decode($rawRepoData, true);
        return $repoData;
    }

    protected function ensureNotExpired(array $metadata, \DateTimeImmutable $now) : void
    {
        // @TODO compare date in $metadata with $now and throw ExpiredMetadataException
        // metadataTimestampToDatetime() in #18 will help with this.
    }

    /**
     * Gets the metadata for $role from durable storage if it is current according to $expectedVersionInfo.
     *
     * @param array $role
     * @param array $expectedVersionInfo
     * @return array|null
     *   The metadata from durable storage if it is current, otherwise null.
     */
    protected function getLocalMetadataIfCurrent(string $role, array $expectedVersionInfo)
    {
        $localRoleMetadata = $this->durableStorage[$this->roleToFilename($role)] ?? null;
        if ($localRoleMetadata === null) {
            return null;
        }
        // TODO Do something less quick & dirty to get useful types from durable storage. See issue #xx
        $localRoleMetadata = json_decode($localRoleMetadata, true);
        $localRole = $localRoleMetadata['signed'];

        if ((int)$expectedVersionInfo['version'] === $localRole['version']) {
            return $localRoleMetadata;
        }
        return null;
    }

    /**
     * Constructs the metadata filename for a role by appending the format extension.
     *
     * Currently only .json is supported, so this always returns ${role}.json
     *
     * @param string $role
     * @return string
     */
    protected function roleToFilename(string $role) : string
    {
        return "${role}.json";
    }

    /**
     * Finds hash algorithms that are secure and available in this environment.
     *
     * @return array
     */
    private function buildSupportedHashAlgosList() : array
    {
        $secureAlgos = ['sha256', 'sha512'];
        $availableAlgos = hash_algos();
        return array_intersect($secureAlgos, $availableAlgos);
    }

    // To be replaced by HTTP / HTTP abstraction layer to the remote repository
    private function getRepoFile($string)
    {
        try {
          // @todo Ensure the file does not exceed a certain size to prevent DOS attacks.
            return file_get_contents(__DIR__ .  "/../../fixtures/tufrepo/metadata/$string");
        } catch (\Exception $exception) {
            return false;
        }
    }
}

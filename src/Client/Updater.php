<?php

namespace Tuf\Client;

use phpDocumentor\Reflection\Types\Static_;
use Tuf\Client\DurableStorage\DurableStorageAccessValidator;
use Tuf\Exception\FormatException;
use Tuf\Exception\PotentialAttackException\FreezeAttackException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
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
     * The remote repo file fetcher.
     *
     * @var \Tuf\Client\RemoteRepoFileFetcherInterface
     */
    protected $remoteRepo;

    /**
     * Updater constructor.
     *
     * @param \Tuf\Client\RemoteRepoFileFetcherInterface $remoteRepo
     *     The remote repo fetcher.
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
     */
    public function __construct(RemoteRepoFileFetcherInterface $remoteRepo, array $mirrors, \ArrayAccess $durableStorage)
    {
        $this->remoteRepo = $remoteRepo;
        $this->mirrors = $mirrors;
        $this->durableStorage = new DurableStorageAccessValidator($durableStorage);
    }

    /**
     * @todo Add docs. See python comments:
     *     https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
     * @todo The Python implementation has an optional flag to "unsafely update
     *     root if necessary". Do we need it?
     *
     * @return boolean
     *     TRUE if the data was successfully refreshed.
     *
     * @see https://github.com/php-tuf/php-tuf/issues/21
     */
    public function refresh() : bool
    {
        $rootData = json_decode($this->durableStorage['root.json'], true);
        $signed = $rootData['signed'];

        $this->roleDB = RoleDB::createRoleDBFromRootMetadata($signed);
        $this->keyDB = KeyDB::createKeyDBFromRootMetadata($signed);


        // SPEC: 1.1.
        $version = (int) $signed['version'];


        // SPEC: 1.2.
        $nextVersion = $version + 1;
        $nextRootContents = $this->remoteRepo->fetchFile("$nextVersion.root.json", static::MAXIMUM_DOWNLOAD_BYTES);
        if ($nextRootContents) {
            // @todo 🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥Add steps do root rotation spec
            //     steps 1.3 -> 1.7.
            // Not production ready🙀.
            throw new \Exception("Root rotation not implemented.");
        }

        // SPEC: 1.8.
        $expires = $signed['expires'];
        $fakeNow = '2020-08-04T02:58:56Z';

        $expireDate = $this->metadataTimestampToDateTime($expires);
        $nowDate = $this->metadataTimestampToDateTime($fakeNow);

        if ($nowDate > $expireDate) {
            throw new \Exception("Root has expired. Potential freeze attack!");
            // @todo "On the next update cycle, begin at step 0 and version N
            //    of the root metadata file."
        }

        // @todo Implement spec 1.9. Does this step rely on root rotation?

        // SPEC: 1.10. Will be used in spec step 4.3.
        //$consistent = $rootData['consistent'];

        // SPEC: 2
        $timestampContents = $this->remoteRepo->fetchFile('timestamp.json', static::MAXIMUM_DOWNLOAD_BYTES);
        $timestampStructure = json_decode($timestampContents, true);
        // SPEC: 2.1
        $this->checkSignatures($timestampStructure, 'timestamp');


        // SPEC: 2.2
        $currentStateTimestamp = json_decode($this->durableStorage['timestamp.json'], true);
        $this->checkRollbackAttack($currentStateTimestamp['signed'], $timestampStructure['signed']);

        // SPEC: 2.3
        $this->checkFreezeAttack($timestampStructure['signed'], $nowDate);
        $durableStorage['timestamp.json'] = $timestampContents;

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
    protected function metadataTimestampToDateTime(string $timestamp) : \DateTimeImmutable
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
     * @param mixed[] $localMetadata
     *     The locally stored metadata from the most recent update.
     * @param mixed[] $remoteMetadata
     *     The latest metadata fetched from the remote repository.
     *
     * @return void
     *
     * @throws RollbackAttackException
     *     Thrown if a potential rollback attack is detected.
     * @throws \UnexpectedValueException
     *     Thrown if metadata types are not the same.
     */
    protected function checkRollbackAttack(array $localMetadata, array $remoteMetadata) : void
    {
        if ($localMetadata['_type'] !== $remoteMetadata['_type']) {
            throw new \UnexpectedValueException('\Tuf\Client\Updater::checkRollbackAttack() can only be used to compare metadata files of the same type. '
               . "Local is {$localMetadata['_type']} and remote is {$remoteMetadata['_type']}.");
        }
        $type = $localMetadata['_type'];
        $localVersion = (int) $localMetadata['version'];
        if ($localVersion === 0) {
            // Failsafe: If local metadata just doesn't have a version property
            //  or it is not an integer, we can't perform this check properly.
            $message = "Empty or invalid local timestamp version \"${localMetadata['version']}\"";
            throw new RollbackAttackException($message);
        }
        $remoteVersion = (int) $remoteMetadata['version'];
        if ($remoteVersion < $localVersion) {
            $message = "Remote $type metadata version \"${remoteMetadata['version']}\"" .
                " is less than previously seen $type version \"${localMetadata['version']}\"";
            throw new RollbackAttackException($message);
        }
    }

    /**
     * Checks for a freeze attack.
     *
     * Verifies that metadata has not expired, and assumes a potential freeze
     * attack if it has.
     *
     * @param mixed[] $metadata
     *     The metadata for the timestamp role.
     * @param \DateTimeInterface $now
     *     The current date and time at runtime.
     *
     * @return void
     *
     * @throws FreezeAttackException
     *     Thrown if a potential freeze attack is detected.
     */
    protected function checkFreezeAttack(array $metadata, \DateTimeInterface $now) : void
    {
        $metadataExpiration = $this->metadataTimestampToDatetime($metadata['expires']);
        if (empty($metadata['_type'])) {
            throw new \UnexpectedValueException('All metadata files must set a value for "_type"');
        }
        if ($metadataExpiration < $now) {
            $format = "Remote %s metadata expired on %s";
            throw new FreezeAttackException(sprintf($format, $metadata['_type'], $metadataExpiration->format('c')));
        }
    }

    /**
     * Checks signatures on a verifiable structure.
     *
     * @param array $verifiableStructure
     *     The canonical array structure of the decoded JSON from a repository
     *     metadata file (e.g., 'timestamp.json').
     * @param string $roleName
     *     The role name for which to check signatures (e.g. 'root',
     *     'timestamp', etc.).
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if the signature thresold has not be reached.
     */
    protected function checkSignatures(array $verifiableStructure, string $roleName) : void
    {
        $signatures = $verifiableStructure['signatures'];
        $signed = $verifiableStructure['signed'];

        $roleInfo = $this->roleDB->getRoleInfo($roleName);
        $needVerified = $roleInfo['threshold'];
        $haveVerified = 0;

        $canonicalBytes = JsonNormalizer::asNormalizedJson($signed);
        foreach ($signatures as $signature) {
            if ($this->isKeyIdAcceptableForRole($signature['keyid'], $roleName)) {
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
            throw new SignatureThresholdExpception("Signature threshold not met on $roleName");
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
     * @param string[] $signatureMeta
     *     The associative metadata array for the signature. Each signature
     *     metadata array contains two elements:
     *     - keyid: The identifier of the key signing the role data.
     *     - sig: The hex-encoded signature of the canonical form of the
     *       metadata for the role.
     *
     * @return boolean
     *     TRUE if the signature is valid for the.
     */
    protected function verifySingleSignature(string $bytes, array $signatureMeta)
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
}

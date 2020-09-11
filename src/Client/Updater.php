<?php

namespace Tuf\Client;

use Tuf\Client\DurableStorage\DurableStorageAccessValidator;
use Tuf\Exception\FormatException;
use Tuf\Exception\PotentialAttackException\FreezeAttackException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\KeyDB;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\TimestampMetadata;
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
     * Updater constructor.
     *
     * @param string $repositoryName
     *     A name the application assigns to the repository used by this
     *     Updater.
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
    public function __construct(string $repositoryName, array $mirrors, \ArrayAccess $durableStorage)
    {
        $this->repoName = $repositoryName;
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
        $rootData = RootMetadata::createFromJson($this->durableStorage['root.json']);

        $this->roleDB = RoleDB::createRoleDBFromRootMetadata($rootData->getSigned());
        $this->keyDB = KeyDB::createKeyDBFromRootMetadata($rootData->getSigned());


        // SPEC: 1.1.
        $version = $rootData->getVersion();


        // SPEC: 1.2.
        $nextVersion = $version + 1;
        $nextRootContents = $this->getRepoFile("$nextVersion.root.json");
        if ($nextRootContents) {
            // @todo 🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥Add steps do root rotation spec
            //     steps 1.3 -> 1.7.
            // Not production ready🙀.
            throw new \Exception("Root rotation not implemented.");
        }

        // SPEC: 1.8.
        $expires = $rootData->getExpires();
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
        $timestampData = TimestampMetadata::createFromJson($this->getRepoFile('timestamp.json'));
        // SPEC: 2.1
        $this->checkSignatures($timestampData);


        // SPEC: 2.2
        $currentStateTimestampData = TimestampMetadata::createFromJson($this->durableStorage['timestamp.json']);
        $this->checkRollbackAttack($currentStateTimestampData, $timestampData);

        // SPEC: 2.3
        $this->checkFreezeAttack($timestampData, $nowDate);
        // @todo Why is the branch adding this back? It should not have changed???
        //$durableStorage['timestamp.json'] = $timestampContents;

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
     * @param \Tuf\Metadata\MetadataBase $localMetadata
     *     The locally stored metadata from the most recent update.
     * @param \Tuf\Metadata\MetadataBase $remoteMetadata
     *     The latest metadata fetched from the remote repository.
     *
     * @return void
     *
     * @throws RollbackAttackException
     *     Thrown if a potential rollback attack is detected.
     * @throws \UnexpectedValueException
     *     Thrown if metadata types are not the same.
     */
    protected function checkRollbackAttack(MetadataBase $localMetadata, MetadataBase $remoteMetadata)
    {
        if ($localMetadata->getType() !== $remoteMetadata->getType()) {
            throw new \UnexpectedValueException('\Tuf\Client\Updater::checkRollbackAttack() can only be used to compare metadata files of the same type. '
               . "Local is {$localMetadata->getType()} and remote is {$remoteMetadata->getType()}.");
        }
        $type = $localMetadata->getType();
        $localVersion = $localMetadata->getVersion();
        $remoteVersion = $remoteMetadata->getVersion();
        if ($remoteVersion < $localVersion) {
            $message = "Remote $type metadata version \"$" . $remoteMetadata->getVersion() .
                "\" is less than previously seen $type version \"$" . $localMetadata->getVersion() . '"';
            throw new RollbackAttackException($message);
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
    protected function checkFreezeAttack(MetadataBase $metadata, \DateTimeInterface $now)
    {
        $metadataExpiration = $this->metadataTimestampToDatetime($metadata->getExpires());
        if ($metadataExpiration < $now) {
            $format = "Remote %s metadata expired on %s";
            throw new FreezeAttackException(sprintf($format, $metadata->getType(), $metadataExpiration->format('c')));
        }
    }

    /**
     * Checks signatures on a verifiable structure.
     *
     * @param \Tuf\Metadata\MetadataBase $metaData
     *     The metadata to check signatures on.
     * @param string $roleName
     *     The role name for which to check signatures (e.g. 'root',
     *     'timestamp', etc.).
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if the signature thresold has not be reached.
     */
    protected function checkSignatures(MetadataBase $metaData) : void
    {
        $signatures = $metaData->getSignatures();

        $roleInfo = $this->roleDB->getRoleInfo($metaData->getType());
        $needVerified = $roleInfo['threshold'];
        $haveVerified = 0;

        $canonicalBytes = JsonNormalizer::asNormalizedJson($metaData->getSigned());
        foreach ($signatures as $signature) {
            if ($this->isKeyIdAcceptableForRole($signature['keyid'], $metaData->getType())) {
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
            throw new SignatureThresholdExpception("Signature threshold not met on " . $metaData->getType());
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

    /**
     * To be replaced by HTTP / HTTP abstraction layer to the remote repository.
     *
     * @param string $filename
     *     The filename within the fixture repo.
     *
     * @return string|false
     *     The contents of the file, or FALSE if the file could not be
     *     retrieved.
     */
    private function getRepoFile(string $filename)
    {
        try {
            // @todo Ensure the file does not exceed a certain size to prevent
            //     DOS attacks.
            return file_get_contents(__DIR__ .  "/../../fixtures/tufrepo/metadata/$filename");
        } catch (\Exception $exception) {
            return false;
        }
    }
}

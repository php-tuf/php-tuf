<?php


namespace Tuf\Client;

use Tuf\Client\DurableStorage\FileStorage;
use Tuf\Client\DurableStorage\DurableStorageAccessValidator;
use Tuf\Exception\FormatException;
use Tuf\Exception\PotentialAttackException\FreezeAttackException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
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

    protected $keyRegistry;

  /**
   * Updater constructor.
   *
   * @param string $repositoryName
   * @param array[][] $mirrors
   *   Re
   */
    public function __construct($repositoryName, $mirrors)
    {
        $this->repoName = $repositoryName;
        $this->mirrors = $mirrors;
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
        // @TODO Inject DurableStorage
        $durableStorage = new DurableStorageAccessValidator(
            new FileStorage(__DIR__ . "/../../fixtures/tufrepo/metadata")
        );
        $rootData = json_decode($durableStorage['root.json'], true);
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
        $expireDate = $this->metadataTimestampToDatetime($expires);
        $nowDate = $this->metadataTimestampToDatetime($fakeNow);
        if ($nowDate > $expireDate) {
            throw new \Exception("Root has expired. Potential freeze attack!");
            // @todo "On the next update cycle, begin at step 0 and version N of the
            //   root metadata file."
        }

        // @todo Implement spec 1.9. Does this step rely on root rotation?

        // SPEC: 1.10. Will be used in spec step 4.3.
        //$consistent = $rootData['consistent'];

        // SPEC: 2
        $timestampContents = $this->getRepoFile('timestamp.json');
        $timestampStructure = json_decode($timestampContents, true);
        // SPEC: 2.1
        if (! $this->checkSignatures($timestampStructure, 'timestamp')) {
            throw new \Exception("Improperly signed repository timestamp.");
        }

        // SPEC: 2.2
        $currentStateTimestamp = json_decode($durableStorage['timestamp.json'], true);
        $this->checkRollbackAttack($currentStateTimestamp['signed'], $timestampStructure['signed']);

        // SPEC: 2.3
        $this->checkFreezeAttack($timestampStructure['signed'], $nowDate);
        $durableStorage['timestamp.json'] = $timestampContents;

        return true;
    }

    protected function metadataTimestampToDatetime(string $timestamp) : \DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", $timestamp);
        if ($dt === false) {
            throw new FormatException($timestamp, "Could not be interpreted as a DateTime");
        }
        return $dt;
    }

    /**
     * Verifies that an incoming remote version of a metadata file is >= the last known version.
     *
     * @param $localMetadata
     * @param $remoteMetadata
     * @throws RollbackAttackException
     */
    protected function checkRollbackAttack(array $localMetadata, array $remoteMetadata)
    {
        $localVersion = $localMetadata['version'] + 0;
        if ($localVersion == 0) {
            // Failsafe: if local metadata just doesn't have a version property or it is not an integer,
            // we can't perform this check properly.
            throw new RollbackAttackException("Empty or invalid local timestamp version \"${localMetadata['version']}\"");
        }
        if ($remoteMetadata['version'] + 0 < $localVersion) {
            throw new RollbackAttackException("Remote timestamp metadata version \"${remoteMetadata['version']}\" is less than previously seen timestamp version \"${localMetadata['version']}\"");
        }
    }

    /**
     * Verifies that metadata has not expired, and assumes a potential freeze attack if it has.
     *
     * @param array $metadata
     * @param \DateTimeInterface $now
     * @throws FreezeAttackException
     */
    protected function checkFreezeAttack(array $metadata, \DateTimeInterface $now)
    {
        $metadataExpiration = $this->metadataTimestampToDatetime($metadata['expires']);
        $type = '';
        if (!empty($metadata['_type'])) {
            $type = $metadata['_type'];
        }
        if ($metadataExpiration < $now) {
            throw new FreezeAttackException(sprintf("Remote %s metadata expired on %s", $type, $metadataExpiration->format('c')));
        }
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

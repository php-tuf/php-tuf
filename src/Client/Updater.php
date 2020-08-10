<?php


namespace Tuf\Client;

use Tuf\Client\DurableStorage\FilesystemDurableStorage;
use Tuf\Client\DurableStorage\ValidatingArrayAccessAdapter;
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
    }

  /**
   * @todo Add docs. See python comments:
   *     https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
   * @todo The Python implementation has an optional flag to "unsafely update
   *     root if necessary". Do we need it?
   *
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
        // @todo investigate whether we in fact need multiple simultaneous
        //   repository support.
        RepositoryDBCollection::singleton()->setDatabasesForRepository($keyDB, $roleDB, 'default');

        // SPEC: 1.1.
        $version = (int) $signed['version'];


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
        $expires = $signed['expires'];
        $fakeNow = '2020-08-04T02:58:56Z';
        $expireDate = \DateTime::createFromFormat("Y-m-dTH:i:sZ", $fakeNow);
        $nowDate = \DateTime::createFromFormat("Y-m-dTH:i:sZ", $expires);
        if ($nowDate > $expireDate) {
            throw new \Exception("Root has expired. Potential freeze attack!");
            // @todo "On the next update cycle, begin at step 0 and version N
            //    of the root metadata file."
        }

        // @todo Implement spec 1.9. Does this step rely on root rotation?

        // SPEC: 1.10. Will be used in spec step 4.3.
        //$consistent = $rootData['consistent'];

        // SPEC: 2
        $timestampContents = $this->getRepoFile('timestamp.json');
        $timestampStructure = json_decode($timestampContents, true);
        // SPEC: 2.1
        if (! $this->checkSignatures($timestampStructure, 'timestamp')) {
          // Exception? Log + return false?
            throw new \Exception("Improperly signed repository timestamp.");
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
            // @todo Determine if we should check all signatures and warn for
            //     bad signatures even this method returns TRUE because the
            //     threshold has been met.
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
        // @todo check that the key type in $signatureMeta is ed25519; return
        //     false if not.
        return \sodium_crypto_sign_verify_detached($sigBytes, $bytes, $pubkeyBytes);
    }

    // To be replaced by HTTP / HTTP abstraction layer to the remote repository
    private function getRepoFile($string)
    {
        try {
            // @todo Ensure the file does not exceed a certain size to prevent
            //     DOS attacks.
            return file_get_contents(__DIR__ .  "/../../fixtures/tufrepo/metadata/$string");
        } catch (\Exception $exception) {
            return false;
        }
    }
}

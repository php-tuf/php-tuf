<?php


namespace Tuf\Client;

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
   * @param string $repository_name
   * @param array[][] $mirrors
   *   Re
   */
    public function __construct($repository_name, $mirrors)
    {
        $this->repoName = $repository_name;
        $this->mirrors = $mirrors;
    }


  /**
   * @todo Update from python comment https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
   *
   * @param bool $unsafely_update_root_if_necessary
   */
    public function refresh($unsafely_update_root_if_necessary = true)
    {
    }

  /**
   * Validates a target.
   *
   * @param $target_repo_path
   * @param $target_stream
   *
   * @return bool
   *   Returns true if the target validates.
   */
    public function validateTarget($target_repo_path, $target_stream)
    {
        // @TODO source original "step 0" root data from client state, not repository
        $root_data = json_decode($this->getRepoFile('root.json'), true);
        $signed = $root_data['signed'];

        $roleDB = RoleDB::createRoleDBFromRootMetadata($signed);
        $keyDB = KeyDB::createKeyDBFromRootMetadata($signed);
        // @TODO investigate whether we in fact need multiple simultaneous repository support.
        RepositoryDBCollection::singleton()->setDatabasesForRepository($keyDB, $roleDB, 'default');

        // SPEC: 1.1.
        $version = (int) $signed['version'];


        // SPEC: 1.2.
        $next_version = $version + 1;
        $next_root_contents = $this->getRepoFile("$next_version.root.json");
        if ($next_root_contents) {
          // @todo 🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥Add steps do root rotation spec steps 1.3 -> 1.7.
          //  Not production ready🙀.
            throw new \Exception("Root rotation not implemented.");
        }

        // SPEC: 1.8.
        $expires = $signed['expires'];
        $fake_now = '2020-08-04T02:58:56Z';
        $expire_date = \DateTime::createFromFormat("Y-m-dTH:i:sZ", $fake_now);
        $now_date = \DateTime::createFromFormat("Y-m-dTH:i:sZ", $expires);
        if ($now_date > $expire_date) {
            throw new \Exception("Root has expired. Potential freeze attack!");
          // @todo "On the next update cycle, begin at step 0 and version N of the
          //   root metadata file."
        }

        // @todo Implement spec 1.9. Does this step rely on root rotation?

        // SPEC: 1.10. Will be used in spec step 4.3.
        //$consistent = $root_data['consistent'];

        // SPEC: 2
        $timestamp_contents = $this->getRepoFile('timestamp.json');
        $timestamp_structure = json_decode($timestamp_contents, true);
        // SPEC: 2.1
        if (! $this->checkSignatures($timestamp_structure, 'timestamp')) {
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

        $canonical_bytes = JsonNormalizer::asNormalizedJson($signed);
        foreach ($signatures as $signature) {
            if ($this->isKeyIdAcceptableForRole($signature['keyid'], $type)) {
                $haveVerified += (int)$this->verifySingleSignature($canonical_bytes, $signature);
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
            return file_get_contents(__DIR__ .  "/../../fixtures/tufclient/tufrepo/metadata/current/$string");
        } catch (\Exception $exception) {
            return false;
        }
    }
}

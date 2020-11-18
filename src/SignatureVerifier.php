<?php


namespace Tuf;


use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;

class SignatureVerifier
{

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
     * SignatureVerifier constructor.
     */
    private function __construct(RoleDB $roleDB, KeyDB $keyDB)
    {
        $this->roleDB = $roleDB;
        $this->keyDB = $keyDB;
    }

    public static function createFromRootMetadata(RootMetadata $rootMetadata) {
        return new static(RoleDB::createFromRootMetadata($rootMetadata), KeyDB::createFromRootMetadata($rootMetadata));
    }

    /**
     * Checks signatures on a verifiable structure.
     *
     * @param array $metaData
     *     The metadata to check signatures on.
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\SignatureThresholdExpception
     *   Thrown if the signature thresold has not be reached.
     */
    public function checkSignatures(\ArrayObject $metaData) : void
    {
        // ☹️ we have to assume a lot about the signed data. We could write more
        // validation logic to make sure object version is correct but since
        // we are reading it anyways it seems to take away alot of the benefit
        // doing the signature verfication first. Because we have to read it aways
        // to do the signature verification.
        $signatures = $metaData['signatures'];

        $type = $metaData['signed']['_type'];
        $roleInfo = $this->roleDB->getRoleInfo($type);
        $needVerified = $roleInfo['threshold'];
        $haveVerified = 0;

        $canonicalBytes = JsonNormalizer::asNormalizedJson($metaData['signed']);
        foreach ($signatures as $signature) {
            if ($this->isKeyIdAcceptableForRole($signature['keyid'], $type)) {
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
            throw new SignatureThresholdExpception("Signature threshold not met on " . $type);
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
    protected function verifySingleSignature(string $bytes, \ArrayObject $signatureMeta)
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
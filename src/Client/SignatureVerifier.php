<?php

namespace Tuf\Client;

use Tuf\Exception\Attack\SignatureThresholdException;
use Tuf\Exception\InvalidKeyException;
use Tuf\Exception\NotFoundException;
use Tuf\Key;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Role;
use Tuf\RoleDB;

/**
 * A class that verifies metadata signatures.
 */
final class SignatureVerifier
{
    /**
     * @var \Tuf\Key[]
     */
    private array $keys = [];

    /**
     * SignatureVerifier constructor.
     */
    private function __construct(private RoleDB $roleDb)
    {
    }

    /**
     * Creates a SignatureVerifier object from a RootMetadata object.
     *
     * @param \Tuf\Metadata\RootMetadata $rootMetadata
     * @param bool $allowUntrustedAccess
     *
     * @return static
     */
    public static function createFromRootMetadata(RootMetadata $rootMetadata, bool $allowUntrustedAccess = false): static
    {
        $instance = new static(
            RoleDB::createFromRootMetadata($rootMetadata, $allowUntrustedAccess),
        );
        foreach ($rootMetadata->getKeys($allowUntrustedAccess) as $keyId => $key) {
            $instance->addKey($keyId, $key);
        }
        return $instance;
    }

    /**
     * Checks signatures on a verifiable structure.
     *
     * @param \Tuf\Metadata\MetadataBase $metadata
     *     The metadata to check signatures on.
     *
     * @return void
     *
     * @throws \Tuf\Exception\Attack\SignatureThresholdException
     *   Thrown if the signature threshold has not be reached.
     */
    public function checkSignatures(MetadataBase $metadata): void
    {
        $signatures = $metadata->getSignatures();

        $role = $this->roleDb->getRole($metadata->getRole());
        $needVerified = $role->getThreshold();
        $verifiedKeySignatures = [];

        foreach ($signatures as $signature) {
            // Don't allow the same key to be counted twice.
            if ($role->isKeyIdAcceptable($signature['keyid']) && $this->verifySingleSignature($metadata->toCanonicalJson(), $signature)) {
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
            throw new SignatureThresholdException("Signature threshold not met on " . $metadata->getRole());
        }
    }

    /**
     * Verifies a single signature.
     *
     * @param string $bytes
     *     The canonical JSON string of the 'signed' section of the given file.
     * @param array $signatureMeta
     *     The ArrayAccess object of metadata for the signature. Each signature
     *     metadata contains two elements:
     *     - keyid: The identifier of the key signing the role data.
     *     - sig: The hex-encoded signature of the canonical form of the
     *       metadata for the role.
     *
     * @return boolean
     *     TRUE if the signature is valid for $bytes.
     *
     * @throws \Tuf\Exception\NotFoundException
     *   If the public key for the given signature has not been added.
     */
    private function verifySingleSignature(string $bytes, array $signatureMeta): bool
    {
        // Get the pubkey from the key database.
        $keyId = $signatureMeta['keyid'];
        $pubkey = $this->keys[$keyId]?->getPublic() ?? throw new NotFoundException($keyId, 'key');

        // Encode the pubkey and signature, and check that the signature is
        // valid for the given data and pubkey.
        $pubkeyBytes = \sodium_hex2bin($pubkey);
        $sigBytes = \sodium_hex2bin($signatureMeta['sig']);
        return \sodium_crypto_sign_verify_detached($sigBytes, $bytes, $pubkeyBytes);
    }

    /**
     * Adds a role to the signature verifier.
     *
     * @param \Tuf\Role $role
     */
    public function addRole(Role $role): void
    {
        if (!$this->roleDb->roleExists($role->getName())) {
            $this->roleDb->addRole($role);
        }
    }

    /**
     * Adds a key to the signature verifier.
     *
     * @param string $keyId
     * @param \Tuf\Key $key
     *
     * @see https://theupdateframework.github.io/specification/v1.0.29#document-formats
     */
    public function addKey(string $keyId, Key $key): void
    {
        // Per TUF specification 4.3, Clients MUST calculate each KEYID to
        // verify this is correct for the associated key.
        if ($keyId !== $key->getComputedKeyId()) {
            throw new InvalidKeyException('The calculated KEYID does not match the value provided.');
        }
        $this->keys[$keyId] = $key;
    }
}

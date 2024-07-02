<?php

namespace Tuf;

use Tuf\Metadata\ConstraintsTrait;

/**
 * Class that represents key metadata.
 */
final class Key
{
    use CanonicalJsonTrait;
    use ConstraintsTrait;

    /**
     * Key constructor.
     *
     * @param string $type
     *   The key type.
     * @param string $scheme
     *   The key scheme.
     * @param string $public
     *   The public key value.
     */
    private function __construct(public readonly string $type, private string $scheme, public readonly string $public)
    {
    }

    /**
     * Creates a key object from TUF metadata.
     *
     * @param array $keyInfo
     *   The key information from TUF metadata, including:
     *   - keytype: The public key signature system, e.g. 'ed25519'.
     *   - scheme: The corresponding signature scheme, e.g. 'ed25519'.
     *   - keyval: An associative array containing the public key value.

     *
     * @return static
     *
     * @see https://theupdateframework.github.io/specification/v1.0.33#document-formats
     */
    public static function createFromMetadata(array $keyInfo): self
    {
        self::validate($keyInfo, static::getKeyConstraints());
        return new static(
            $keyInfo['keytype'],
            $keyInfo['scheme'],
            $keyInfo['keyval']['public']
        );
    }

    /**
     * Computes the key ID.
     *
     * Per specification section 4.2, the KEYID is a hexdigest of the SHA-256
     * hash of the canonical form of the key.
     *
     * @return string
     *     The key ID in hex format for the key metadata hashed using sha256.
     *
     * @see https://theupdateframework.github.io/specification/v1.0.33#document-formats
     *
     * @todo https://github.com/php-tuf/php-tuf/issues/56
     */
    public function getComputedKeyId(): string
    {
        // @see https://github.com/secure-systems-lab/securesystemslib/blob/master/securesystemslib/keys.py
        $canonical = self::encodeJson([
            'keytype' => $this->type,
            'scheme' => $this->scheme,
            'keyval' => [
                'public' => $this->public,
            ],
        ]);
        return hash('sha256', $canonical);
    }
}

<?php


namespace Tuf;

use DeepCopy\DeepCopy;
use Tuf\Exception\NotFoundException;
use Tuf\Metadata\RootMetadata;

/**
 * Represent a collection of keys and their organization.
 *
 * This class ensures the layout of the collection remains consistent and
 * easily verifiable. Keys are set/get in this class primarily by their key ID.
 * Key IDs are used as identifiers for keys and are hexadecimal representations
 * of the hash of key objects.  See computeKeyIds() to learn precisely how
 * key IDs are generated.  One may get the key ID of a key object by accessing
 * the array's 'keyid' key (i.e., $keyMeta['keyid']).
 *
 * @see https://github.com/theupdateframework/tuf/blob/292b18926b45106b27f582dc3cb1433363d03a9a/tuf/keydb.py
 */
class KeyDB
{
    /**
     * Keys indexed by key ID.
     *
     * @var \array[]
     */
    protected $keys;

    /**
     * Creates a key database with the unique keys found in root metadata.
     *
     * @param \Tuf\Metadata\RootMetadata $rootMetadata
     *    The root metadata.
     * @param boolean $allowUntrustedAccess
     *   Whether this method should access even if the metadata is not trusted.
     *
     * @return \Tuf\KeyDB
     *     The constructed key database object.
     *
     * @throws \Exception
     *   Thrown if an unsupported key type exists in the metadata.
     *
     * @see https://github.com/theupdateframework/specification/blob/v1.0.9/tuf-spec.md#4-document-formats
     */
    public static function createFromRootMetadata(RootMetadata $rootMetadata, bool $allowUntrustedAccess = false): KeyDB
    {
        $db = new self();

        foreach ($rootMetadata->getKeys($allowUntrustedAccess) as $keyMeta) {
            $db->addKey($keyMeta);
        }

        return $db;
    }

    /**
     * Gets the supported encryption key types.
     *
     * @return string[]
     *     An array of supported encryption key type names (e.g. 'ed25519').
     *
     * @see src/constants.php
     */
    public static function getSupportedKeyTypes(): array
    {
        static $types = [];
        if (count($types) == 0) {
            $types = explode(" ", SUPPORTED_KEY_TYPES);
        }
        return $types;
    }

    /**
     * Computes the hashed keys IDs for the given key metadata.
     *
     * @param \ArrayAccess $keyMeta
     *     An ArrayAccess object of key metadata. See self::addKey() and the TUF
     *     specification for the array structure.
     *
     * @return string
     *     The hashed key ID for the key metadata using sha256.
     *
     * @see https://github.com/theupdateframework/specification/blob/v1.0.9/tuf-spec.md#4-document-formats
     *
     * @todo https://github.com/php-tuf/php-tuf/issues/56
     */
    private static function computeKeyId(\ArrayAccess $keyMeta): string
    {
        // @see https://github.com/secure-systems-lab/securesystemslib/blob/master/securesystemslib/keys.py
        // The keyid_hash_algorithms array value is based on the TUF settings,
        // it's not expected to be part of the key data. The fact that it is
        // currently included is a quirk of the TUF python code that may be
        // fixed in future versions. For simplicity, hard-code it using the
        // normal TUF settings.
        $keyCanonicalStruct = [
            'keytype' => $keyMeta['keytype'],
            'scheme' => $keyMeta['scheme'],
            'keyid_hash_algorithms' => ['sha256', 'sha512'],
            'keyval' => ['public' => $keyMeta['keyval']['public']],
        ];
        $keyCanonicalForm = JsonNormalizer::asNormalizedJson($keyCanonicalStruct);

        return hash('sha256', $keyCanonicalForm, false);
    }

    /**
     * Constructs a new KeyDB.
     */
    public function __construct()
    {
        $this->keys = [];
    }

    /**
     * Adds key metadata to the key database while avoiding duplicates.
     *
     * @param \ArrayAccess $keyMeta
     *     An associative array of key metadata, including:
     *     - keytype: The public key signature system, e.g. 'ed25519'.
     *     - scheme: The corresponding signature scheme, e.g. 'ed25519'.
     *     - keyval: An associative array containing the public key value.
     *     - keyid_hash_algorithms: @todo This differs from the spec. See
     *       linked issue.
     *
     * @return void
     *
     * @see https://github.com/theupdateframework/specification/blob/v1.0.9/tuf-spec.md#4-document-formats
     *
     * @todo https://github.com/php-tuf/php-tuf/issues/56
     */
    private function addKey(\ArrayAccess $keyMeta): void
    {
        if (! in_array($keyMeta['keytype'], self::getSupportedKeyTypes(), true)) {
            // @todo Convert this to a log line as per Python.
            // https://github.com/php-tuf/php-tuf/issues/160
            throw new \Exception("Root metadata file contains an unsupported key type: \"${keyMeta['keytype']}\"");
        }
        // One key ID for each $keyMeta['keyid_hash_algorithms'].
        $computedKeyId = self::computeKeyId($keyMeta);
        $this->keys[$computedKeyId] = $keyMeta;
    }

    /**
     * Returns the key metadata for a given key ID.
     *
     * @param string $keyId
     *     The key ID.
     *
     * @return \ArrayObject
     *     The key metadata matching $keyId. See self::addKey() and the TUF
     *     specification for the array structure.
     *
     * @throws \Tuf\Exception\NotFoundException
     *     Thrown if the key ID is not found in the keydb database.
     *
     * @see https://github.com/theupdateframework/specification/blob/v1.0.9/tuf-spec.md#4-document-formats
     */
    public function getKey(string $keyId): \ArrayObject
    {
        if (empty($this->keys[$keyId])) {
            throw new NotFoundException($keyId, 'key');
        }
        return (new DeepCopy())->copy($this->keys[$keyId]);
    }
}

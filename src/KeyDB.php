<?php


namespace Tuf;

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
     *
     * @return \Tuf\KeyDB
     *     The constructed key database object.
     *
     * @throws \Exception
     *   Thrown if an unsupported key type exists in the metadata.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     */
    public static function createFromRootMetadata(RootMetadata $rootMetadata)
    {
        $db = new self();

        foreach ($rootMetadata->getKeys() as $keyMeta) {
            if (! in_array($keyMeta['keytype'], self::getSupportedKeyTypes(), true)) {
                // @todo Convert this to a log line as per Python.
                throw new \Exception("Root metadata file contains an unsupported key type: \"${keyMeta['keytype']}\"");
            }
            // One key ID for each $keyMeta['keyid_hash_algorithms'].
            $computedKeyIds = self::computeKeyIds($keyMeta);
            foreach ($computedKeyIds as $keyId) {
                $keyMeta['keyid'] = $keyId;
                $db->addKey($keyMeta);
            }
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
    public static function getSupportedKeyTypes()
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
     * @param array $keyMeta
     *     An associative array of key metadata. See self::addKey() and the TUF
     *     specification for the array structure.
     *
     * @return string[]
     *     An array of hashed key IDs for the key metadata. Each entry is a
     *     string hash of the key signature and all its associated metadata.
     *     There is one entry for each hashing algorithm specified in the
     *     'keyid_hash_algorithms' child array.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     *
     * @todo https://github.com/php-tuf/php-tuf/issues/56
     */
    public static function computeKeyIds(array $keyMeta)
    {
        $keyCanonicalStruct = [
            'keytype' => $keyMeta['keytype'],
            'scheme' => $keyMeta['scheme'],
            'keyid_hash_algorithms' => $keyMeta['keyid_hash_algorithms'],
            'keyval' => ['public' => $keyMeta['keyval']['public']],
        ];
        $keyCanonicalForm = JsonNormalizer::asNormalizedJson($keyCanonicalStruct);

        // Generate a hash of the key and its metadata for each of the listed
        // keyid_hash_algorithms.
        return array_map(function ($algo) use ($keyCanonicalForm) {
            return hash($algo, $keyCanonicalForm, false);
        }, $keyMeta['keyid_hash_algorithms']);
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
     * @param array $keyMeta
     *     An associative array of key metadata, including:
     *     - keytype: The public key signature system, e.g. 'ed25519'.
     *     - scheme: The corresponding signature scheme, e.g. 'ed25519'.
     *     - keyval: An associative array containing the public key value.
     *     - keyid_hash_algorithms: @todo This differs from the spec. See
     *       linked issue.
     *
     * @return void
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     *
     * @todo https://github.com/php-tuf/php-tuf/issues/56
     */
    public function addKey(array $keyMeta)
    {
        $this->keys[$keyMeta['keyid']] = $keyMeta;
    }

    /**
     * Returns the key metadata for a given key ID.
     *
     * @param string $keyId
     *     The key ID.
     *
     * @return array
     *     The key metadata matching $keyId. See self::addKey() and the TUF
     *     specification for the array structure.
     *
     * @throws \Exception
     *     Thrown if the key ID is not found in the keydb database.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     */
    public function getKey(string $keyId)
    {
        if (empty($this->keys[$keyId])) {
            throw new \Exception("Unknown key ID: $keyId");
        }
        return $this->keys[$keyId];
    }
}

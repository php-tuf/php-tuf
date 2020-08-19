<?php


namespace Tuf;

/**
 * Represent a collection of keys and their organization.
 *
 * This class ensures the layout of the collection remain consistent and easily
 * verifiable. Keys are set/get in this class primarily by their key ID. Key IDs
 * are used as identifiers for keys and are hexadecimal representations of the
 * hash of key objects.  See computeKeyIds() to learn precisely how keyids are
 * generated.  One may get the keyid of a key object by simply accessing the
 * array's 'keyid' key (i.e., $keyMeta['keyid']).
 *
 * @see https://github.com/theupdateframework/tuf/blob/292b18926b45106b27f582dc3cb1433363d03a9a/tuf/keydb.py
 */
class KeyDB
{
    /**
     * @var \array[]
     *
     * Keys indexed by key ID.
     */
    protected $keys;

    /**
     * Creates a key database with the unique keys found in root metadata.
     *
     * @param $rootMetadata
     *    An associative array as one would obtain by decoding json conformant
     *    to section 4.3 of the TUF specification.
     *
     * @return \Tuf\KeyDB
     *
     * @throws \Exception
     *   Thrown if an unsupported key type exists in the metadata.
     */
    public static function createKeyDBFromRootMetadata($rootMetadata)
    {
        $db = new self();

        foreach ($rootMetadata['keys'] as $keyMeta) {
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
     * Gets the supported key types.
     *
     * @return string[]
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
     * Computes the keys ids for the keys in the key metadata.
     *
     * @param $keyMeta
     *
     * @return string[]
     */
    public static function computeKeyIds($keyMeta)
    {
        $keyCanonicalStruct = [
        'keytype' => $keyMeta['keytype'],
        'scheme' => $keyMeta['scheme'],
        'keyid_hash_algorithms' => $keyMeta['keyid_hash_algorithms'],
        'keyval' => ['public' => $keyMeta['keyval']['public']],
        ];
        $keyCanonicalForm = JsonNormalizer::asNormalizedJson($keyCanonicalStruct);
        return array_map(function ($algo) use ($keyCanonicalForm) {
            return hash($algo, $keyCanonicalForm, false);
        }, $keyMeta['keyid_hash_algorithms']);
    }

    public function __construct()
    {
        $this->keys = [];
    }

    /**
     * Adds key metadata to the key database while avoiding duplicates.
     *
     * @param array $keyMeta
     */
    public function addKey($keyMeta)
    {
        $this->keys[$keyMeta['keyid']] = $keyMeta;
    }

    /**
     * Return the key belonging to 'keyid'.
     *
     * @param $keyId
     *     The key id.
     *
     * @return array
     *     The key matching $keyId.
     *
     * @throws \Exception
     *     Thrown if the key id is not found in the keydb database.
     */
    public function getKey($keyId)
    {
        if (empty($this->keys[$keyId])) {
            throw new \Exception("Unknown key ID: $keyId");
        }
        return $this->keys[$keyId];
    }
}

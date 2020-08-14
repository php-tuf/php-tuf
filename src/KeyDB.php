<?php


namespace Tuf;

/**
 * Represent a collection of keys and their organization.  This module ensures
 * the layout of the collection remain consistent and easily verifiable.
 * Provided are functions to add and delete keys from the database, retrieve a
 * single key, and assemble a collection from keys stored in TUF 'Root'
 * Metadata. The Update Framework process maintains a set of role info for
 * multiple repositories.
 *
 * RSA keys are currently supported and a collection of keys is organized as a
 * dictionary indexed by key ID.  Key IDs are used as identifiers for keys
 * (e.g., RSA key).  They are the hexadecimal representations of the hash of
 * key
 * objects (specifically, the key object containing only the public key).  See
 * 'rsa_key.py' and the '_get_keyid()' function to learn precisely how keyids
 * are generated.  One may get the keyid of a key object by simply accessing
 * the
 * dictionary's 'keyid' key (i.e., rsakey['keyid']).
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
     * Populate the key database with the unique keys found in 'root_metadata'.
     * The database dictionary will conform to
     * 'tuf.formats.KEYDB_SCHEMA' and have the form: {keyid: key,
     * ...}.  The 'keyid' conforms to 'securesystemslib.formats.KEYID_SCHEMA' and
     * 'key' to its respective type.  In the case of RSA keys, this object would
     * match 'RSAKEY_SCHEMA'.
     *
     * @param $rootMetadata
     *    A dictionary conformant to 'tuf.formats.ROOT_SCHEMA'.  The keys found
     *    in the 'keys' field of 'root_metadata' are needed by this function.
     *
     * @return \Tuf\KeyDB
     * @throws \Exception
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

    public static function getSupportedKeyTypes()
    {
        static $types = [];
        if (count($types) == 0) {
            $types = explode(" ", SUPPORTED_KEY_TYPES);
        }
        return $types;
    }

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
     * Add 'rsakey_dict' to the key database while avoiding duplicates.
     * If keyid is provided, verify it is the correct keyid for 'rsakey_dict'
     * and raise an exception if it is not.
     *
     * @param $keyMeta
     */
    public function addKey($keyMeta)
    {
        $this->keys[$keyMeta['keyid']] = $keyMeta;
    }

    /**
     * Return the key belonging to 'keyid'.
     *
     * @param $keyId
     *     An object conformant to 'securesystemslib.formats.KEYID_SCHEMA'. It
     *     is used as an identifier for keys.
     *
     * @return array
     *     The key matching 'keyid'.  In the case of RSA keys, a dictionary
     *     conformant to 'securesystemslib.formats.RSAKEY_SCHEMA' is returned.
     *
     * @throws \Exception
     *     Thrown if 'keyid' is not found in the keydb database.
     */
    public function getKey($keyId)
    {
        if (empty($this->keys[$keyId])) {
            throw new \Exception("Unknown key ID: $keyId");
        }
        return $this->keys[$keyId];
    }
}

<?php


namespace Tuf;

class KeyDB
{
  /**
   * @var \array[]
   *
   * Keys indexed by key id.
   */
    protected $keys;

    public static function createKeyDBFromRootMetadata($rootMetadata)
    {
        $db = new self();

        foreach ($rootMetadata['keys'] as $keyMeta) {
            if (! in_array($keyMeta['keytype'], self::getSupportedKeyTypes(), true)) {
                throw new \Exception("Root metadata file contains an unsupported key type: \"${keyMeta['keytype']}\"");
            }
          // One key id for each $keyMeta['keyid_hash_algorithms']
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
        static $types = array();
        if (count($types) == 0) {
            $types = explode(" ", SUPPORTED_KEY_TYPES);
        }
        return $types;
    }

    public static function computeKeyIds($keyMeta)
    {
        $keyCanonicalStruct = array(
        'keytype' => $keyMeta['keytype'],
        'scheme' => $keyMeta['scheme'],
        'keyid_hash_algorithms' => $keyMeta['keyid_hash_algorithms'],
        'keyval' => array('public' => $keyMeta['keyval']['public']),
        );
        $keyCanonicalForm = JsonNormalizer::asNormalizedJson($keyCanonicalStruct);
        return array_map(function ($algo) use ($keyCanonicalForm) {
            return hash($algo, $keyCanonicalForm, false);
        }, $keyMeta['keyid_hash_algorithms']);
    }

    public function __construct()
    {
        $this->keys = array();
    }

    public function addKey($keyMeta)
    {
        $this->keys[$keyMeta['keyid']] = $keyMeta;
    }

    public function getKey($keyId)
    {
        if (empty($this->keys[$keyId])) {
            throw new \Exception("Unknown key id: $keyId");
        }
        return $this->keys[$keyId];
    }
}

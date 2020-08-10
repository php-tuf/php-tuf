<?php


namespace Tuf;

class JsonNormalizer
{
  /**
   * Computes the canonical json representation of an associative array as a string.
   *
   * @todo This is a very incomplete implementation of
   *     http://wiki.laptop.org/go/Canonical_JSON.
   *     Consider creating a separate library under php-tuf just for this?
   *
   * @param array $structure
   *
   * @return string
   */
    public static function asNormalizedJson($structure)
    {
        if (!is_array($structure)) {
            throw new \Exception("Array of keys to canonicalize is required");
        }

        self::rKeySort($structure);

        return json_encode($structure);
    }

    private static function rKeySort(&$structure)
    {
        if (!ksort($structure, SORT_STRING)) {
            throw new \Exception("Failure sorting keys, canonicalization is not possible.");
        }

        foreach ($structure as $item => $value) {
            if (is_array($value)) {
                self::rKeySort($structure[$item]);
            }
        }
    }
}

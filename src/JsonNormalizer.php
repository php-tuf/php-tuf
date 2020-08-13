<?php

namespace Tuf;

/**
 * Provdes normalization to convert an array to a canonical JSON string.
 */
class JsonNormalizer
{
    /**
     * Encodes an associative array into a string of canonical JSON.
     *
     * @param mixed[] $structure
     *     The associative array of JSON data.
     *
     * @return string
     *     An encoded string of normalized, canonical JSON data.
     *
     * @todo This is a very incomplete implementation of
     *     http://wiki.laptop.org/go/Canonical_JSON.
     *     Consider creating a separate library under php-tuf just for this?
     */
    public static function asNormalizedJson(array $structure) : string
    {
        self::rKeySort($structure);

        return json_encode($structure);
    }

    /**
     * Sorts the JSON data array into a canonical order.
     *
     * @param mixed[] $structure
     *     The array of JSON to sort, passed by reference.
     *
     * @throws \Exception
     *     Thrown if sorting the array fails.
     *
     * @return void
     */
    private static function rKeySort(array &$structure) : void
    {
        if (!ksort($structure, SORT_STRING)) {
            throw new \Exception("Failure sorting keys. Canonicalization is not possible.");
        }

        foreach ($structure as $item => $value) {
            if (is_array($value)) {
                self::rKeySort($structure[$item]);
            }
        }
    }
}

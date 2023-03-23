<?php

namespace Tuf;

/**
 * Provides normalization to convert an array to a canonical JSON string.
 *
 * @internal
 *   This is not a generic normalizer but intended to be used PHP-TUF metadata
 *   classes.
 */
class JsonNormalizer
{
    /**
     * Encodes an associative array into a string of canonical JSON.
     *
     * @param mixed[]|\stdClass $structure
     *     The associative array of JSON data.
     *
     * @return string
     *     An encoded string of normalized, canonical JSON data.
     *
     * @todo This is a very incomplete implementation of
     *     http://wiki.laptop.org/go/Canonical_JSON.
     *     Consider creating a separate library under php-tuf just for this?
     *     https://github.com/php-tuf/php-tuf/issues/14
     */
    public static function asNormalizedJson(iterable $structure): string
    {
        self::rKeySort($structure);
        return json_encode($structure, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Sorts the JSON data array into a canonical order.
     *
     * @param iterable $structure
     *     The JSON data to sort, passed by reference.
     *
     * @throws \Exception
     *     Thrown if sorting the array fails.
     * @throws \RuntimeException
     *     Thrown if an object other than \ArrayObject is found.
     *
     * @return void
     */
    public static function rKeySort(iterable &$structure): void
    {
        if (is_array($structure)) {
            if (!ksort($structure, SORT_STRING)) {
                throw new \Exception("Failure sorting keys. Canonicalization is not possible.");
            }
        } elseif ($structure instanceof \ArrayObject) {
            $structure->ksort();
        } elseif (is_object($structure)) {
            throw new \RuntimeException('\Tuf\JsonNormalizer::rKeySort() is not intended to sort objects except \ArrayObject. Found: ' . get_class($structure));
        }

        foreach ($structure as $key => $value) {
            if (is_array($value) || $value instanceof \ArrayObject) {
                self::rKeySort($structure[$key]);
            }
        }
    }
}

<?php

namespace Tuf;

use Tuf\Metadata\ValidatableClass;

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
     */
    public static function asNormalizedJson($structure) : string
    {
        self::rKeySort($structure);
        return json_encode($structure);
    }

    /**
     * Decodes a string to data that can be used with ::asNormalizedJson().
     *
     * @param string $json
     *   The JSON string.
     *
     * @return mixed
     *   The decoded data.
     */
    public static function decode(string $json)
    {
        $data = json_decode($json);
        static::convertToSortableAndValidatable($data);
        return $data;
    }

    /**
     * Sorts the JSON data array into a canonical order.
     *
     * @param mixed[]|\stdClass $structure
     *     The array of JSON to sort, passed by reference.
     *
     * @throws \Exception
     *     Thrown if sorting the array fails.
     *
     * @return void
     */
    private static function rKeySort(&$structure) : void
    {
        if (is_array($structure)) {
            if (!ksort($structure, SORT_STRING)) {
                throw new \Exception("Failure sorting keys. Canonicalization is not possible.");
            }
        } elseif ($structure instanceof ValidatableClass) {
            $structure->ksort();
        } elseif (is_object($structure)) {
            throw new \Exception('\Tuf\JsonNormalizer::rKeySort() not intended to sort objects except \Tuf\Metadata\ValidatableClass found: ' . get_class($structure));
        }

        foreach ($structure as $key => $value) {
            if (is_array($value) || $value instanceof ValidatableClass) {
                if (is_array($structure)) {
                    self::rKeySort($structure[$key]);
                } elseif ($structure instanceof ValidatableClass) {
                    $original = $structure->offsetGet($key, true);
                    self::rKeySort($original);
                }
            }
        }
    }

    /**
     * Converts an data structure to validate by Symfony Validator library.
     *
     * @param array|\stdClass $data
     *   The data to convert.
     *
     *
     * @return void
     */
    private static function convertToSortableAndValidatable(&$data):void
    {
        if ($data instanceof \stdClass) {
            $data = new ValidatableClass($data);
        } elseif (!is_array($data)) {
            throw new \RuntimeException('Cannot convert type: ' . get_class($data));
        }
        foreach ($data as $key => $datum) {
            if (is_array($datum) || is_object($datum)) {
                static::convertToSortableAndValidatable($datum);
            }
            $data[$key] = $datum;
        }
    }
}

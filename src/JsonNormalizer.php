<?php

namespace Tuf;

use mysql_xdevapi\Exception;
use Tuf\Metadata\ValidatableClass;

/**
 * Provdes normalization to convert an array to a canonical JSON string.
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

    public static function decode(string $string)
    {
        $data = json_decode($string);
        static::convertToSortableAndValidable($data);
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
        }
        elseif (is_object($structure)) {
            throw new Exception('\Tuf\JsonNormalizer::rKeySort() not intended to sort objects except \Tuf\Metadata\ValidatableClass found: ' . get_class($structure));
        }

        foreach ($structure as $key => $value) {
            if (is_array($value) || $value instanceof ValidatableClass) {
                if (is_array($structure)) {
                    self::rKeySort($structure[$key]);
                }
                elseif ($structure instanceof ValidatableClass) {
                    $original = $structure->offsetGet($key, true);
                    self::rKeySort($original);
                }
            }
        }
    }

    /**
     *
     * @param array|\stdClass|\ArrayAccess $data
     */
    private static function convertToSortableAndValidable(&$data)
    {
        if ($data instanceof \stdClass) {
            $data = new ValidatableClass($data);
        }
        foreach ($data as $key => $datum) {
            if ($datum instanceof \stdClass) {
                $datum = new ValidatableClass($datum);
            }
            if (is_array($datum) || $datum instanceof ValidatableClass) {
                static::convertToSortableAndValidable($datum);
            }
            $data[$key] = $datum;
        }
    }
}

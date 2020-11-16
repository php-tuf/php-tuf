<?php


namespace Tuf\Tests\TestHelpers;

/**
 * General test utility helper trait.
 */
trait UtilsTrait
{

    /**
     * Gets the real path of repository fixtures.
     *
     * @param string $fixturesSet
     *   The fixtures set to use.
     * @param string $path
     *   The path.
     * @param boolean $isDir
     *   Whether $path is expected to be a directory.
     *
     * @return string
     *   The path.
     */
    public static function getFixturesRealPath(string $fixturesSet, string $path, bool $isDir = true)
    {
        $realpath = realpath(__DIR__ . "/../../fixtures/$fixturesSet/$path");
        if ($realpath === false || ($isDir && !is_dir($realpath))) {
            throw new \RuntimeException("Repository fixtures directory not found at $path");
        }
        return $realpath;
    }

    /**
     * Helper methods for dataProvider methods to return keyed arrays.
     *
     * @param array $providedData
     *   The dataProvider data.
     *
     * @param integer|null $useArgumentNumber
     *   (optional) The argument to user the key.
     *
     * @return array
     *   The new keyed array where the keys are string concatenation of the
     *   arguments.
     */
    protected static function getKeyedArray(array $providedData, int $useArgumentNumber = null)
    {
        $newData = [];
        foreach ($providedData as $arguments) {
            $key = '';
            if ($useArgumentNumber !== null) {
                $key = (string) $arguments[$useArgumentNumber];
            } else {
                foreach ($arguments as $argument) {
                    if (is_numeric($argument) || is_string($argument)) {
                        $key .= '-' . (string) $argument;
                    }
                }
            }

            if (isset($newData[$key])) {
                throw new \RuntimeException("Cannot produce unique keys");
            }
            $newData[$key] = $arguments;
        }
        return $newData;
    }

    /**
     * Change a nested array element.
     *
     * @param array $keys
     *   Ordered keys to the value to set.
     * @param array $data
     *   The array to modify.
     * @param mixed $newValue
     *   The new value to set.
     *
     * @return void
     */
    protected function nestedChange(array $keys, &$data, $newValue) : void
    {
        $key = array_shift($keys);
        if ($keys) {
            $this->nestedChange($keys, $data[$key], $newValue);
        } else {
            $data[$key] = $newValue;
        }
    }
}

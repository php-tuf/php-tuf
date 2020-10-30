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
     * @param string $path
     *   The path.
     *
     * @param boolean $isDir
     *   Whether $path is expected to be a directory.
     *
     * @return string
     *   The path.
     */
    public static function getFixturesRealPath(string $path, bool $isDir = true, string $fixturesSet = 'delegated')
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
                    $key .= '-' . (string) $argument;
                }
            }

            if (isset($newData[$key])) {
                throw new \RuntimeException("Cannot produce unique keys");
            }
            $newData[$key] = $arguments;
        }
        return $newData;
    }
}

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
    public static function getFixturesRealPath(string $path, bool $isDir = true)
    {
        $realpath = realpath(__DIR__ . "/../../fixtures/$path");
        if ($realpath === false || ($isDir && !is_dir($realpath))) {
            throw new \RuntimeException("Repository fixtures directory not found at $path");
        }
        return $realpath;
    }
}

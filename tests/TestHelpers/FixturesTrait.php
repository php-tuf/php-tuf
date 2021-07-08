<?php

namespace Tuf\Tests\TestHelpers;

use PHPUnit\Framework\Assert;

/**
 * Contains methods for safely interacting with the test fixtures.
 */
trait FixturesTrait
{
    /**
     * Gets the real path of repository fixtures.
     *
     * @param string $fixtureName
     *   The fixtures set to use.
     * @param string $subPath
     *   The path.
     * @param boolean $isDir
     *   Whether $path is expected to be a directory.
     *
     * @return string
     *   The path.
     */
    private static function getFixturePath(string $fixtureName, string $subPath = '', bool $isDir = true): string
    {
        $realpath = realpath(__DIR__ . "/../../fixtures/$fixtureName/$subPath");
        Assert::assertNotEmpty($realpath);

        if ($isDir) {
            Assert::assertDirectoryExists($realpath);
        } else {
            Assert::assertFileExists($realpath);
        }
        return $realpath;
    }
}

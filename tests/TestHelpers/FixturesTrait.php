<?php

namespace Tuf\Tests\TestHelpers;

use PHPUnit\Framework\Assert;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage;

/**
 * Contains methods for safely interacting with the test fixtures.
 */
trait FixturesTrait {

    /**
     * The initial client-side metadata versions for all fixtures.
     *
     * @var array[]
     */
    private static $initialMetadataVersions = [
        'TUFTestFixtureDelegated' => [
            'root' => 2,
            'timestamp' => 2,
            'snapshot' => 2,
            'targets' => 2,
            'unclaimed' => 1,
        ],
        'TUFTestFixtureUnsupportedDelegation' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'unsupported_target' => NULL,
            // We cannot assert the starting versions of 'targets' because it
            // has an unsupported field and would throw an exception when
            // validating.
        ],
        'TUFTestFixtureSimple' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixtureAttackRollback' => [
            'root' => 2,
            'timestamp' => 2,
            'snapshot' => 2,
            'targets' => 2,
        ],
        'TUFTestFixtureThresholdTwo' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixtureThresholdTwoAttack' => [
            'root' => 2,
            'timestamp' => 2,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixtureNestedDelegated' => [
            'root' => 2,
            'timestamp' => 2,
            'snapshot' => 2,
            'targets' => 2,
            'unclaimed' => 1,
            'level_2' => NULL,
            'level_3' => NULL,
        ],
        'TUFTestFixtureTerminatingDelegation' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixtureTopLevelTerminating' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixtureNestedTerminatingNonDelegatingDelegation' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixture3LevelDelegation' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'TUFTestFixtureNestedDelegatedErrors' => [
            'root' => 2,
            'timestamp' => 2,
            'snapshot' => 2,
            'targets' => 2,
            'unclaimed' => 1,
        ],
    ];


    /**
     * Uses test fixtures at a given path to populate a memory storage backend.
     *
     * @param string $fixtureName
     *     The name of the fixture to use.
     * @param string $path
     *     The relative path (within the fixtures directory) for the client
     *     data.
     *
     * @return MemoryStorage
     *     Memory storage containing the test client data.
     */
    private function loadFixtureIntoMemory(string $fixtureName, string $path = 'client/metadata/current'): MemoryStorage
    {
        $storage = new MemoryStorage();

        // Loop through and load files in the given path.
        $fsIterator = new \FilesystemIterator(
            static::getFixturePath($fixtureName, $path, true),
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME
        );
        foreach ($fsIterator as $filename => $info) {
            // Only load JSON files.
            /** @var $info \SplFileInfo */
            if ($info->isFile() && preg_match("|\.json$|", $filename)) {
                $storage[$filename] = file_get_contents($info->getRealPath());
            }
        }

        return $storage;
    }

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
        }
        else {
            Assert::assertFileExists($realpath);
        }
        return $realpath;
    }
}

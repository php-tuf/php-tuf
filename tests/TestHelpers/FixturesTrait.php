<?php

namespace Tuf\Tests\TestHelpers;

use PHPUnit\Framework\Assert;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage;

/**
 * Contains methods for safely interacting with the test fixtures.
 */
trait FixturesTrait
{
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
            'unsupported_target' => null,
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
            'level_2' => null,
            'level_3' => null,
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
        'PublishedTwice' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'PublishedTwiceWithRotatedKeys_timestamp' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
        'PublishedTwiceWithRotatedKeys_snapshot' => [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ],
    ];

    /**
     * Uses test fixtures at a given path to populate a memory storage backend.
     *
     * @param string $fixtureName
     *     The name of the fixture to use.
     * @param string $path
     *     An optional relative sub-path within the fixture's directory.
     *     Defaults to the directory containing client metadata.
     *
     * @return MemoryStorage
     *     Memory storage containing the test data.
     */
    private static function loadFixtureIntoMemory(string $fixtureName, string $path = 'client/metadata/current'): MemoryStorage
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
        } else {
            Assert::assertFileExists($realpath);
        }
        return $realpath;
    }

    /**
     * Asserts that stored metadata are at expected versions.
     *
     * @param ?int[] $expectedVersions
     *   The expected versions. The keys are the file names, without the .json
     *   suffix, and the values are the expected version numbers, or NULL if
     *   the file should not be present.
     * @param \ArrayAccess $storage
     *   The durable storage for the metadata.
     *
     * @return void
     */
    private static function assertMetadataVersions(array $expectedVersions, \ArrayAccess $storage): void
    {
        foreach ($expectedVersions as $role => $version) {
            if (is_null($version)) {
                Assert::assertNull($storage["$role.json"], "'$role' file is null.");
                return;
            }
            $roleJson = $storage["$role.json"];
            Assert::assertNotNull($roleJson, "'$role.json' not found in local repo.");
            switch ($role) {
                case 'root':
                    $metadata = RootMetadata::createFromJson($roleJson);
                    break;
                case 'timestamp':
                    $metadata = TimestampMetadata::createFromJson($roleJson);
                    break;
                case 'snapshot':
                    $metadata = SnapshotMetadata::createFromJson($roleJson);
                    break;
                default:
                    // Any other roles will be 'targets' or delegated targets roles.
                    $metadata = TargetsMetadata::createFromJson($roleJson);
                    break;
            }
            $actualVersion = $metadata->getVersion();
            Assert::assertSame(
                $expectedVersions[$role],
                $actualVersion,
                "Actual version of $role, '$actualVersion' does not match expected version '$version'"
            );
        }
    }
}

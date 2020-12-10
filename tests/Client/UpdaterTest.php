<?php

namespace Tuf\Tests\Client;

use phpDocumentor\Reflection\Types\Integer;
use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

class UpdaterTest extends TestCase
{
    use MemoryStorageLoaderTrait;

    /**
     * The local repo.
     *
     * @var \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage
     */
    protected $localRepo;

    /**
     * @var \Tuf\Tests\Client\TestRepo
     */
    protected $testRepo;

    /**
     * Gets the metadata start versions for a fixture set.
     *
     * @param string $fixturesSet
     *   The fixture set name.
     *
     * @return int[]
     *   The expected metadata start versions for the fixture set.
     */
    private static function getFixtureClientStartVersions(string $fixturesSet): array
    {
        $startVersions = [
            'TUFTestFixtureDelegated' => [
                'root' => 3,
                'timestamp' => 3,
                'snapshot' => 3,
                'targets' => 3,
            ],
            'TUFTestFixtureSimple' => [
                'root' => 2,
                'timestamp' => 2,
                'snapshot' => 2,
                'targets' => 2,
            ],
        ];
        if (!isset($startVersions[$fixturesSet])) {
            throw new \UnexpectedValueException("Unknown fixture set: $fixturesSet");
        }
        return $startVersions[$fixturesSet];
    }

    /**
     * Returns a memory-based updater populated with the test fixtures.
     *
     * @return Updater
     *     The test updater, which uses the 'current' test fixtures in the
     *     tufclient/tufrepo/metadata/current/ directory and a localhost HTTP
     *     mirror.
     */
    protected function getSystemInTest() : Updater
    {
        $mirrors = [
            'mirror1' => [
                'url_prefix' => 'http://localhost:8001',
                'metadata_path' => 'metadata',
                'targets_path' => 'targets',
                'confined_target_dirs' => [],
            ],
        ];

        // Remove all '*.[TYPE].json' because they are needed for the tests.
        $fixtureFiles = scandir($this->getFixturesRealPath('TUFTestFixtureDelegated', 'tufclient/tufrepo/metadata/current'));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/.*\..*\.json/', $fileName)) {
                unset($this->localRepo[$fileName]);
            }
        }
        $updater = new Updater($this->testRepo, $mirrors, $this->localRepo);
        return $updater;
    }

    /**
     * Tests refreshing the repository.
     *
     * @param string $fixturesSet
     *   The fixtures set to use.
     * @param array $expectedUpdatedVersions
     *   The expected updated versions.
     *
     * @return void
     *
     * @dataProvider providerRefreshRepository
     */
    public function testRefreshRepository(string $fixturesSet, array $expectedUpdatedVersions) : void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        $updater = $this->getSystemInTest();
        $this->assertTrue($updater->refresh());
        // Confirm the root was updated to version 5 which is the highest
        // version in the test fixtures.
        $this->assertClientRepoVersions($expectedUpdatedVersions);
    }

    /**
     * Dataprovider for testRefreshRepository().
     *
     * @return mixed[]
     *   The data set for testRefreshRepository().
     */
    public function providerRefreshRepository()
    {
        return $this->getKeyedArray([
            [
                'TUFTestFixtureDelegated',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                ],
            ],
        ], 0);
    }

    /**
     * Asserts that files in the client repo are at expected versions.
     *
     * @param array $expectedVersions
     *   The expected versions.
     *
     * @return void
     */
    protected function assertClientRepoVersions(array $expectedVersions): void
    {
        foreach ($expectedVersions as $type => $version) {
            if (is_null($version)) {
                $this->assertNull($this->localRepo["$type.json"]);
                return;
            }
            switch ($type) {
                case 'root':
                    $metaData = RootMetadata::createFromJson($this->localRepo["$type.json"]);
                    break;
                case 'timestamp':
                    $metaData = TimestampMetadata::createFromJson($this->localRepo["$type.json"]);
                    break;
                case 'snapshot':
                    $metaData = SnapshotMetadata::createFromJson($this->localRepo["$type.json"]);
                    break;
                case 'targets':
                    $metaData = TargetsMetadata::createFromJson($this->localRepo["$type.json"]);
                    break;
                default:
                    $this->fail("Unexpected type: $type");
            }
            $actualVersion = $metaData->getVersion();
            $this->assertSame(
                $expectedVersions[$type],
                $actualVersion,
                "Actual version of $type, '$actualVersion' does not match expected version '$version'"
            );
        }
    }


    /**
     * Tests that an exception is thrown when attempting to refresh the repository with file that has an invalid valid
     * signature.
     *
     * @param string $fileToFail
     *   The repo fail that should fail the signature check.
     * @param integer $expectedRootVersion
     *   The expected root version after the refresh attempt.
     * @param string $expectionMessage
     *   The expected exception message.
     *
     * @return void
     *
     * @dataProvider providerSignatureError
     */
    public function testSignatureError(string $fileToFail, int $expectedRootVersion, string $expectionMessage) : void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture('TUFTestFixtureDelegated', 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo('TUFTestFixtureDelegated');
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions('TUFTestFixtureDelegated'));
        $this->testRepo->setRepoFileNestedValue($fileToFail);
        $updater = $this->getSystemInTest();
        try {
            $updater->refresh();
        } catch (SignatureThresholdExpception $exception) {
            // Confirm the root was updated to version 4 but not to 5 because
            // 5.root.json should throw an exception.
            $this->assertSame(
                $expectedRootVersion,
                RootMetadata::createFromJson($this->localRepo['root.json'])->getVersion()
            );
            $this->assertSame($expectionMessage, $exception->getMessage());
            return;
        }
        $this->fail('No SignatureThresholdExpception thrown');
    }

    /**
     * Dataprovider for testSignatureError().
     *
     * @return array[]
     *   The test cases for testSignatureError().
     */
    public function providerSignatureError()
    {
        return $this->getKeyedArray([
            [
                '4.root.json',
                3,
                'Signature threshold not met on root',
            ],
            [
                '5.root.json',
                4,
                'Signature threshold not met on root',
            ],
            [
                'timestamp.json',
                5,
                'Signature threshold not met on timestamp',
            ],
        ], 0);
    }

    /**
     * Tests that if a file is missing from the repo an exception is thrown.
     *
     * @param string $fixturesSet
     *   The fixtures set to use.
     * @param string $fileName
     *   The name of the file to remove from the repo.
     * @param array $expectedUpdatedVersions
     *   The expected updated versions.
     *
     * @return void
     *
     * @dataProvider providerFileNotFoundExceptions
     */
    public function testFileNotFoundExceptions(string $fixturesSet, string $fileName, array $expectedUpdatedVersions):void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        $this->testRepo->removeRepoFile($fileName);
        $updater = $this->getSystemInTest();
        try {
            $updater->refresh();
        } catch (RepoFileNotFound $exception) {
            $this->assertSame("File $fileName not found.", $exception->getMessage());
            $this->assertClientRepoVersions($expectedUpdatedVersions);
            return;
        }
        $this->fail('No RepoFileNotFound exception thrown');
    }

    /**
     * Data provider for testFileNotFoundExceptions().
     *
     * @return mixed[]
     *   The test cases for testFileNotFoundExceptions().
     */
    public function providerFileNotFoundExceptions():array
    {
        return $this->getKeyedArray([
            [
                'TUFTestFixtureDelegated',
                'timestamp.json',
                [
                    'root' => 5,
                    'timestamp' => null,
                    'snapshot' => null,
                    'targets' => 5,
                ],
            ],
            [
                'TUFTestFixtureDelegated',
                '5.snapshot.json',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => null,
                    'targets' => 5,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                'timestamp.json',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                '2.snapshot.json',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
        ]);
    }
}

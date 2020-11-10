<?php

namespace Tuf\Tests\Client;

use phpDocumentor\Reflection\Types\Integer;
use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Exception\MetadataException;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
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
        $fixtureFiles = scandir($this->getFixturesRealPath('delegated', 'tufclient/tufrepo/metadata/current'));
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
     * @param array $expectedStartVersions
     *   The expected start versions.
     * @param array $expectedUpdatedVersions
     *   The expected updated versions.
     *
     * @return void
     *
     * @dataProvider providerRefreshRepository
     */
    public function testRefreshRepository(string $fixturesSet, array $expectedStartVersions, array $expectedUpdatedVersions) : void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertRepoVersions($expectedStartVersions);
        $updater = $this->getSystemInTest();
        $this->assertTrue($updater->refresh());
        // Confirm the root was updated to version 5 which is the highest
        // version in the test fixtures.
        $this->assertRepoVersions($expectedUpdatedVersions);
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
                'delegated',
                [
                    'root' => 3,
                    'timestamp' => 3,
                    'snapshot' => 3,
                    'targets' => 3,
                ],
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                ],
            ],
            [
                'simple',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
                [
                    'root' => 3,
                    'timestamp' => 3,
                    'snapshot' => 3,
                    'targets' => 3,
                ],
            ],
        ], 0);
    }

    /**
     * Asserts that files in the repo are at expected versions.
     *
     * @param array $expectedVersions
     *   The expected versions.
     *
     * @return void
     */
    protected function assertRepoVersions(array $expectedVersions): void
    {
        foreach ($expectedVersions as $type => $version) {
            if (is_null($version)) {
                $this->assertNull($this->localRepo["$type.json"]);
                return;
            }
            $actualVersion = MetadataBase::createFromJson($this->localRepo["$type.json"])->getVersion();
            $this->assertSame(
                $expectedVersions[$type],
                $actualVersion,
              "Actual verison of $type, '$actualVersion' does not match expected version '$version'"
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
        $this->localRepo = $this->memoryStorageFromFixture('delegated', 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo('delegated');
        $this->assertRepoVersions(['root' => 3]);
        $this->testRepo->setFileChange($fileToFail);
        $updater = $this->getSystemInTest();
        try {
            $updater->refresh();
        } catch (SignatureThresholdExpception $exception) {
            // Confirm the root was updated to version 4 but not to 5 because
            // 5.root.json should throw an exception.
            $this->assertRepoVersions(['root' => $expectedRootVersion]);
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
     * @param string $fileToChange
     * @param array $keys
     * @param $newValue
     *
     * @dataProvider providerFileMetaDataException
     */
    public function testFileMetaDataException(string $fileToChange, array $keys, $newValue, string $expectedMessage, array $expectedVersions): void {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture('delegated', 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo('delegated');
        $this->assertRepoVersions(['root' => 3]);
        $this->testRepo->setFileChange($fileToChange, $keys, $newValue);
        $updater = $this->getSystemInTest();
        try {
            $updater->refresh();
        } catch (MetadataException $exception) {
            // Confirm the root was updated to version 4 but not to 5 because
            // 5.root.json should throw an exception.
            $this->assertSame($expectedMessage, $exception->getMessage());
            $this->assertRepoVersions($expectedVersions);
            return;
        }
        $this->fail('No MetadataException thrown');
    }

    /**
     * Data provider for testFileMetaDataException().
     *
     * @return mixed[]
     */
    public function providerFileMetaDataException()
    {
        return static::getKeyedArray([
          [
            '5.snapshot.json',
            ['signed', 'newkey'],
          'new value',
              "The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata.",
            [
              'root' => 5,
              'timestamp' => 5,
              'snapshot' => NULL,
                'targets' => 3,
            ],
          ],
          [
            '5.snapshot.json',
            ['signed', 'version'],
            6,
            "Expected snapshot version 5 does not match actual version 6.",
            [
              'root' => 5,
              'timestamp' => 5,
              'snapshot' => NULL,
              'targets' => 3,
            ],
          ],
          [
            '5.targets.json',
            ['signed', 'version'],
            6,
            "Expected targets version 5 does not match actual version 6.",
            [
              'root' => 5,
              'timestamp' => 5,
              'snapshot' => 5,
              'targets' => 3,
            ],
          ],
        ]);
    }
}

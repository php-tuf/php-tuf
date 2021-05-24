<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\MetadataException;
use Tuf\Exception\NotFoundException;
use Tuf\Exception\PotentialAttackException\InvalidHashException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Exception\TufException;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;
use Tuf\Tests\TestHelpers\TestClock;

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
                'unclaimed' => 1,
            ],
            'TUFTestFixtureUnsupportedDelegation' => [
                'root' => 2,
                'timestamp' => 2,
                'snapshot' => 2,
                'unsupported_target' => null,
              // We cannot assert the starting versions of 'targets' because it has
              // an unsupported field and would throw an exception when validating.
            ],
            'TUFTestFixtureSimple' => [
                'root' => 2,
                'timestamp' => 2,
                'snapshot' => 2,
                'targets' => 2,
            ],
            'TUFTestFixtureAttackRollback' => [
                'root' => 3,
                'timestamp' => 3,
                'snapshot' => 3,
                'targets' => 3,
            ],
            'TUFTestFixtureThresholdTwo' => [
                'root' => 2,
                'timestamp' => 2,
                'snapshot' => 1,
                'targets' => 1,
            ],
            'TUFTestFixtureThresholdTwoAttack' => [
                'root' => 3,
                'timestamp' => 3,
                'snapshot' => 1,
                'targets' => 1,
            ],
            'TUFTestFixtureNestedDelegated' => [
                'root' => 3,
                'timestamp' => 3,
                'snapshot' => 3,
                'targets' => 3,
                'unclaimed' => 1,
                'level_2' => null,
                'level_3' => null,
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
     * @param string $fixturesSet
     *     The fixtures set to use.
     *
     * @return Updater
     *     The test updater, which uses the 'current' test fixtures in the
     *     tufclient/tufrepo/metadata/current/ directory and a localhost HTTP
     *     mirror.
     */
    protected function getSystemInTest(string $fixturesSet = 'TUFTestFixtureDelegated'): Updater
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
        $fixtureFiles = scandir(static::getFixturesRealPath($fixturesSet, 'tufclient/tufrepo/metadata/current'));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/.*\..*\.json/', $fileName)) {
                unset($this->localRepo[$fileName]);
            }
        }
        $updater = new TestUpdater($this->testRepo, $mirrors, $this->localRepo, new TestClock());
        return $updater;
    }

    /**
     * Tests that TUF will transparently verify downloaded target hashes.
     *
     * @covers ::download
     *
     * @return void
     */
    public function testVerifiedDownload(): void
    {
        $fixturesSet = 'TUFTestFixtureSimple';
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest($fixturesSet);

        $testFilePath = static::getFixturesRealPath($fixturesSet, 'tufrepo/targets/testtarget.txt', false);
        $testFileContents = file_get_contents($testFilePath);
        $this->assertSame($testFileContents, $updater->download('testtarget.txt')->wait()->getContents());

        // If the file fetcher returns a file stream, the updater should NOT try
        // to read the contents of the stream into memory.
        $stream = $this->prophesize('\Psr\Http\Message\StreamInterface');
        $stream->getMetadata('uri')->willReturn($testFilePath);
        $stream->getContents()->shouldNotBeCalled();
        $stream->rewind()->shouldNotBeCalled();
        $stream->getSize()->willReturn(strlen($testFileContents));
        $updater->download('testtarget.txt')->wait();

        // If the target isn't known, we should get a rejected promise.
        $promise = $updater->download('void.txt');
        $this->assertInstanceOf(RejectedPromise::class, $promise);

        $stream = Utils::streamFor('invalid data');
        $this->testRepo->repoFilesContents['testtarget.txt'] = new FulfilledPromise($stream);
        try {
            $updater->download('testtarget.txt')->wait();
            $this->fail('Expected InvalidHashException to be thrown, but it was not.');
        } catch (InvalidHashException $e) {
            $this->assertSame("Invalid sha256 hash for testtarget.txt", $e->getMessage());
            $this->assertSame($stream, $e->getStream());
        }

        // If the stream is longer than expected, we should get an exception,
        // whether or not the stream's length is known.
        $stream = $stream = $this->prophesize('\Psr\Http\Message\StreamInterface');
        $stream->getSize()->willReturn(1024);
        $this->testRepo->repoFilesContents['testtarget.txt'] = new FulfilledPromise($stream->reveal());
        try {
            $updater->download('testtarget.txt')->wait();
            $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
        } catch (DownloadSizeException $e) {
            $this->assertSame("testtarget.txt exceeded 24 bytes", $e->getMessage());
        }

        $stream = $stream = $this->prophesize('\Psr\Http\Message\StreamInterface');
        $stream->getSize()->willReturn(null);
        $stream->rewind()->shouldBeCalledOnce();
        $stream->read(24)->willReturn('A nice, long string that is certainly longer than 24 bytes.');
        $stream->eof()->willReturn(false);
        $this->testRepo->repoFilesContents['testtarget.txt'] = new FulfilledPromise($stream->reveal());
        try {
            $updater->download('testtarget.txt')->wait();
            $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
        } catch (DownloadSizeException $e) {
            $this->assertSame("testtarget.txt exceeded 24 bytes", $e->getMessage());
        }
    }

    /**
     * Tests that TUF will transparently verify downloaded target hashes for targets in delegated JSON files.
     *
     * @todo Add test coverage delegated roles that then delegate to other roles in
     *   https://github.com/php-tuf/php-tuf/issues/142
     *
     * @covers ::download
     *
     * @return void
     */
    public function testVerifiedDelegatedDownload(): void
    {
        $fixturesSet = 'TUFTestFixtureNestedDelegated';
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest();

        // Ensure that client downloads only the delegated role JSON files that
        // are needed to find the metadata for the target.
        $expectedClientVersionsAfterDownloads = [
            'level_1_target.txt' => [
                'root' => 6,
                'timestamp' => 6,
                'snapshot' => 6,
                'targets' => 6,
                'unclaimed' => 2,
                'level_2' => null,
                'level_3' => null,
            ],
            'level_1_2_target.txt' => [
                'root' => 6,
                'timestamp' => 6,
                'snapshot' => 6,
                'targets' => 6,
                'unclaimed' => 2,
                'level_2' => 1,
                'level_2_terminating' => null,
                'level_3' => null,
            ],
            'level_1_2_terminating_findable.txt' => [
                'root' => 6,
                'timestamp' => 6,
                'snapshot' => 6,
                'targets' => 6,
                'unclaimed' => 2,
                'level_2' => 1,
                'level_2_terminating' => 1,
                'level_3' => null,
            ],
            'level_1_2_3_below_non_terminating_target.txt' => [
                'root' => 6,
                'timestamp' => 6,
                'snapshot' => 6,
                'targets' => 6,
                'unclaimed' => 2,
                'level_2' => 1,
                'level_2_terminating' => 1,
                'level_3' => 1,
            ],
            // Roles delegated from a terminating role are evaluated.
            // See TUF-SPEC-v1.0.16 Section 5.5.6.2.1 and 5.5.6.2.2.
            'level_1_2_terminating_3_target.txt' => [
                'root' => 6,
                'timestamp' => 6,
                'snapshot' => 6,
                'targets' => 6,
                'unclaimed' => 2,
                'level_2' => 1,
                'level_2_terminating' => 1,
                'level_3' => 1,
                'level_3_below_terminated' => 1,
            ],
        ];
        foreach ($expectedClientVersionsAfterDownloads as $delegatedFile => $expectedClientVersions) {
            $testFilePath = static::getFixturesRealPath($fixturesSet, "tufrepo/targets/$delegatedFile", false);
            $testFileContents = file_get_contents($testFilePath);
            self::assertNotEmpty($testFileContents);
            $this->assertSame($testFileContents, $updater->download($delegatedFile)->wait()->getContents());
            $this->assertClientRepoVersions($expectedClientVersions);
        }
    }

    /**
     * Tests that improperly delegated targets will produce exceptions.
     *
     * @param string $fileName
     *
     * @dataProvider providerDelegationErrors
     */
    public function testDelegationErrors(string $fileName): void
    {
        $fixturesSet = 'TUFTestFixtureNestedDelegatedErrors';
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest();
        $testFilePath = static::getFixturesRealPath($fixturesSet, "tufrepo/targets/$fileName", false);
        self::expectException(NotFoundException::class);
        self::expectExceptionMessage("Target not found: $fileName");
        $updater->download($fileName)->wait();
    }

    /**
     * Data provider for testDelegationErrors().
     *
     * The files used in these test cases are setup in the Python class
     * generate_fixtures.TUFTestFixtureNestedDelegatedErrors().
     *
     * @return \string[][]
     */
    public function providerDelegationErrors(): array
    {
        return [
            // 'level_a.txt' is added via the 'unclaimed' role but this role has
            // `paths: ['level_1_*.txt']` which does not match the file name.
            'no path match' => ['level_a.txt'],
            // 'level_1_3_target.txt' is added via the 'level_2' role which has
            // `paths: ['level_1_2_*.txt']`. The 'level_2' role is delegated from the
            // 'unclaimed' role which has `paths: ['level_1_*.txt']`. The file matches
            // for the 'unclaimed' role but does not match for the 'level_2' role.
            'matches parent delegation' => ['level_1_3_target.txt'],
            // 'level_2_unfindable.txt' is added via the 'level_2_error' role which has
            // `paths: ['level_2_*.txt']`. The 'level_2_error' role is delegated from the
            // 'unclaimed' role which has `paths: ['level_1_*.txt']`. The file matches
            // for the 'level_2_error' role but does not match for the 'unclaimed' role.
            // No files added via the 'level_2_error' role will be found because its
            // 'paths' property is incompatible with the its parent delegation's
            // 'paths' property.
            'delegated path does not match parent' => ['level_2_unfindable.txt'],
            // 'level_2_after_terminating_unfindable.txt' is added via role
            // 'level_2_after_terminating' which is delegated from role at the same level as 'level_2_terminating'
            //  but added after 'level_2_terminating'.
            // Because 'level_2_terminating' is a terminating role its own delegations are evaluated but no other
            // delegations are evaluated after it.
            // See TUF-SPEC-v1.0.16 Section 5.5.6.2.1 and 5.5.6.2.2.
            'delegation is after terminating delegation' => ['level_2_after_terminating_unfindable.txt'],
        ];
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
    public function testRefreshRepository(string $fixturesSet, array $expectedUpdatedVersions): void
    {
        $expectedStartVersion = static::getFixtureClientStartVersions($fixturesSet);
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);

        $this->assertClientRepoVersions($expectedStartVersion);
        $updater = $this->getSystemInTest($fixturesSet);
        $this->assertTrue($updater->refresh($fixturesSet));
        // Confirm the local version are updated to the expected versions.
        $this->assertClientRepoVersions($expectedUpdatedVersions);

        // Create another version of the client that only starts with the root.json file.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        foreach (array_keys($expectedStartVersion) as $role) {
            if ($role !== 'root') {
                // Change the expectation that client will not start with any files other than root.json.
                $expectedStartVersion[$role] = null;
                // Remove all files except root.json.
                unset($this->localRepo["$role.json"]);
            }
        }
        $this->assertClientRepoVersions($expectedStartVersion);
        $updater = $this->getSystemInTest($fixturesSet);
        $this->assertTrue($updater->refresh());
        // Confirm that if we start with only root.json all of the files still
        // update to the expected versions.

        foreach ($expectedUpdatedVersions as $role => $expectedUpdatedVersion) {
            if (!in_array($role, ['root', 'timestamp', 'snapshot', 'targets'])) {
                // Any delegated role metadata files are not fetched during refresh.
                $expectedUpdatedVersions[$role] = null;
            }
        }
        $this->assertClientRepoVersions($expectedUpdatedVersions);
    }

    /**
     * Dataprovider for testRefreshRepository().
     *
     * @return mixed[]
     *   The data set for testRefreshRepository().
     */
    public function providerRefreshRepository(): array
    {
        return static::getKeyedArray([
            [
                'TUFTestFixtureDelegated',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 1,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            [
                'TUFTestFixtureNestedDelegated',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_3' => null,
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
        foreach ($expectedVersions as $role => $version) {
            if (is_null($version)) {
                $this->assertNull($this->localRepo["$role.json"]);
                return;
            }
            switch ($role) {
                case 'root':
                    $metadata = RootMetadata::createFromJson($this->localRepo["$role.json"]);
                    break;
                case 'timestamp':
                    $metadata = TimestampMetadata::createFromJson($this->localRepo["$role.json"]);
                    break;
                case 'snapshot':
                    $metadata = SnapshotMetadata::createFromJson($this->localRepo["$role.json"]);
                    break;
                default:
                    // Any other roles will be 'targets' or delegated targets roles.
                    $metadata = TargetsMetadata::createFromJson($this->localRepo["$role.json"]);
                    break;
            }
            $actualVersion = $metadata->getVersion();
            $this->assertSame(
                $expectedVersions[$role],
                $actualVersion,
                "Actual version of $role, '$actualVersion' does not match expected version '$version'"
            );
        }
    }

    /**
     * Tests that exceptions are thrown when metadata files are not valid.
     *
     * @param string $fileToChange
     *   The file to change.
     * @param array $keys
     *   The nested keys of the element to change.
     * @param mixed $newValue
     *   The new value to set.
     * @param \Exception $expectedException
     *   The expected exception.
     * @param array $expectedUpdatedVersions
     *   The expected repo file version after refresh attempt.
     *
     * @return void
     *
     * @dataProvider providerRefreshException
     */
    public function testRefreshException(string $fileToChange, array $keys, $newValue, \Exception $expectedException, array $expectedUpdatedVersions): void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $fixturesSet = 'TUFTestFixtureDelegated';
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions('TUFTestFixtureDelegated'));
        $this->testRepo->setRepoFileNestedValue($fileToChange, $keys, $newValue);
        $updater = $this->getSystemInTest($fixturesSet);
        try {
            $updater->refresh();
        } catch (TufException $exception) {
            $this->assertEquals($exception, $expectedException);
            $this->assertClientRepoVersions($expectedUpdatedVersions);
            return;
        }
        $this->fail('No exception thrown. Expected: ' . get_class($expectedException));
    }

    /**
     * Data provider for testRefreshException().
     *
     * @return mixed[]
     *   The test cases for testRefreshException().
     */
    public function providerRefreshException(): array
    {
        return static::getKeyedArray([
            [
                '4.root.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdExpception('Signature threshold not met on root'),
                [
                    'root' => 3,
                    'timestamp' => 3,
                    'snapshot' => 3,
                    'targets' => 3,
                ],
            ],
            [
                '5.root.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdExpception('Signature threshold not met on root'),
                [
                    'root' => 4,
                    'timestamp' => 3,
                    'snapshot' => 3,
                    'targets' => 3,
                ],
            ],
            [
                'timestamp.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdExpception('Signature threshold not met on timestamp'),
                [
                    'root' => 5,
                    'timestamp' => null,
                    'snapshot' => 3,
                    'targets' => 3,
                ],
            ],
            // For snapshot.json files, adding a new key or changing the existing version number
            // will result in a MetadataException indicating that the contents hash does not match
            // the hashes specified in the timestamp.json. This is because timestamp.json in the test
            // fixtures contains the optional 'hashes' metadata for the snapshot.json files, and this
            // is checked before the file signatures and the file version number. The order of checking
            // is specified in TUF-SPEC-v1.0.16 Section 5.5.
            [
                '5.snapshot.json',
                ['signed', 'newkey'],
                'new value',
                new MetadataException("The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata."),
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => null,
                    'targets' => 3,
                ],
            ],
            [
                '5.snapshot.json',
                ['signed', 'version'],
                6,
                new MetadataException("The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata."),
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => null,
                    'targets' => 3,
                ],
            ],
            // For targets.json files, adding a new key or changing the existing version number
            // will result in a SignatureThresholdException because currently the test
            // fixtures do not contain hashes for targets.json files in snapshot.json.
            [
                '5.targets.json',
                ['signed', 'newvalue'],
                'value',
                new SignatureThresholdExpception("Signature threshold not met on targets"),
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 3,
                ],
            ],
            [
                '5.targets.json',
                ['signed', 'version'],
                6,
                new SignatureThresholdExpception("Signature threshold not met on targets"),
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 3,
                ],
            ],
        ]);
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
    public function testFileNotFoundExceptions(string $fixturesSet, string $fileName, array $expectedUpdatedVersions): void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        $this->testRepo->removeRepoFile($fileName);
        $updater = $this->getSystemInTest($fixturesSet);
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
    public function providerFileNotFoundExceptions(): array
    {
        return static::getKeyedArray([
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
                'TUFTestFixtureDelegated',
                '5.targets.json',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 3,
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
            [
                'TUFTestFixtureSimple',
                '2.targets.json',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
        ]);
    }


    /**
     * Data provider for testSignatureThresholds().
     *
     * @return mixed[]
     *   The test cases for testSignatureThresholds().
     */
    public function providerTestSignatureThresholds():array
    {
        return [
            ['TUFTestFixtureThresholdTwo'],
            ['TUFTestFixtureThresholdTwoAttack', SignatureThresholdExpception::class],
        ];
    }

    /**
     * Tests fixtures with signature thresholds greater than 1.
     *
     * @param string $fixturesSet
     *   The fixtures set to use.
     * @param string $expectedException
     *   The null or the class name of an expected exception.
     *
     * @return void
     *
     * @dataProvider providerTestSignatureThresholds
     */
    public function testSignatureThresholds(string $fixturesSet, string $expectedException = null)
    {
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest($fixturesSet);
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        if ($expectedException) {
            $this->expectException($expectedException);
        }
        $updater->refresh();
    }

    /**
     * Tests forcing a refresh from the server.
     */
    public function testUpdateRefresh(): void
    {
        $fixturesSet = 'TUFTestFixtureSimple';
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);

        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        $updater = $this->getSystemInTest();
        // This refresh should succeed.
        $updater->refresh();
        // Put the server-side repo into an invalid state.
        $this->testRepo->removeRepoFile('timestamp.json');
        // The updater is already refreshed, so this will return early, and
        // there should be no changes to the client-side repo.
        $updater->refresh();
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        // If we force a refresh, the invalid state of the server-side repo will
        // raise an exception.
        $this->expectException(RepoFileNotFound::class);
        $this->expectExceptionMessage('File timestamp.json not found.');
        $updater->refresh(true);
    }

    /**
     * Tests that an exceptions for an repo with an unsupported field.
     *
     * @return void
     */
    public function testUnsupportedRepo(): void
    {
        $fixtureSet = 'TUFTestFixtureUnsupportedDelegation';
        $this->localRepo = $this->memoryStorageFromFixture($fixtureSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixtureSet);
        $startingTargets = $this->localRepo['targets.json'];
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixtureSet));
        $updater = $this->getSystemInTest();
        try {
            $updater->refresh();
        } catch (MetadataException $exception) {
            $expectedMessage = preg_quote("Object(ArrayObject)[signed][delegations][roles][0][path_hash_prefixes]:", '/');
            $expectedMessage .= ".*This field is not supported.";
            self::assertSame(1, preg_match("/$expectedMessage/s", $exception->getMessage()));
            // Assert that the root, timestamp and snapshot metadata files were updated
            // and that the unsupported_target metadata file was not downloaded.
            $expectedUpdatedVersion = [
                'root' => 3,
                'timestamp' => 3,
                'snapshot' => 3,
                'unsupported_target' => null,
                // We cannot assert the starting versions of 'targets' because it has
                // an unsupported field and would throw an exception when validating.
            ];
            self::assertClientRepoVersions($expectedUpdatedVersion);
            // Ensure that local version of targets has not changed because the
            // server version is invalid.
            self::assertSame($this->localRepo['targets.json'], $startingTargets);
            return;
        }
        $this->fail('No exception thrown.');
    }

    /**
     * Tests that exceptions are thrown when a repo is in a rollback attack state.
     *
     * @param string $fixturesSet
     *   The fixtures set.
     * @param \Exception $expectedException
     *   The expected exception.
     * @param array $expectedUpdatedVersions
     *   The expected repo file version after refresh attempt.
     *
     * @return void
     *
     * @dataProvider providerAttackRepoException
     */
    public function testAttackRepoException(string $fixturesSet, \Exception $expectedException, array $expectedUpdatedVersions): void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'tufclient/tufrepo/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientRepoVersions(static::getFixtureClientStartVersions($fixturesSet));
        $updater = $this->getSystemInTest();
        try {
            // No changes should be made to client repo.
            $this->localRepo->setExceptionOnChange();
            $updater->refresh();
        } catch (TufException $exception) {
            $this->assertEquals($exception, $expectedException);
            $this->assertClientRepoVersions($expectedUpdatedVersions);
            return;
        }
        $this->fail('No exception thrown. Expected: ' . get_class($expectedException));
    }

    /**
     * Data provider for testAttackRepoException().
     * @return array[]
     *   The test cases.
     */
    public function providerAttackRepoException(): array
    {
        return [
            [
                'TUFTestFixtureAttackRollback',
                new RollbackAttackException('Remote timestamp metadata version "$2" is less than previously seen timestamp version "$3"'),
                [
                    'root' => 3,
                    'timestamp' => 3,
                    'snapshot' => 3,
                    'targets' => 3,
                ],
            ],
        ];
    }
}

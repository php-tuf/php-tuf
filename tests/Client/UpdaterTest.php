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
              // We cannot assert the starting versions of 'targets' because it has
              // an unsupported field and would throw an exception when validating.
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
     *     client/metadata/current/ directory and a localhost HTTP
     *     mirror.
     */
    protected function getSystemInTest(string $fixturesSet = 'TUFTestFixtureDelegated', string $updaterClass = TestUpdater::class): Updater
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
        $fixtureFiles = scandir(static::getFixturesRealPath($fixturesSet, 'client/metadata/current'));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/.*\..*\.json/', $fileName)) {
                unset($this->localRepo[$fileName]);
            }
        }
        return new $updaterClass($this->testRepo, $mirrors, $this->localRepo, new TestClock());
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest($fixturesSet);

        $testFilePath = static::getFixturesRealPath($fixturesSet, 'server/targets/testtarget.txt', false);
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
     * @param string $fixturesSet
     *   The fixture set to test.
     * @param string $delegatedFile
     *   The delegated file to download.
     * @param array $expectedFileVersions
     *   The expected client versions after the download.
     *
     * @return void
     * @todo Add test coverage delegated roles that then delegate to other roles in
     *   https://github.com/php-tuf/php-tuf/issues/142
     *
     * @covers ::download
     *
     * @dataProvider providerVerifiedDelegatedDownload
     *
     */
    public function testVerifiedDelegatedDownload(string $fixturesSet, string $delegatedFile, array $expectedFileVersions): void
    {
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest();

        $testFilePath = static::getFixturesRealPath($fixturesSet, "server/targets/$delegatedFile", false);
        $testFileContents = file_get_contents($testFilePath);
        self::assertNotEmpty($testFileContents);
        $this->assertSame($testFileContents, $updater->download($delegatedFile)->wait()->getContents());
        // Ensure that client downloads only the delegated role JSON files that
        // are needed to find the metadata for the target.
        $this->assertClientFileVersions($expectedFileVersions);
    }

    public function providerVerifiedDelegatedDownload(): array
    {
        return [
          // Test cases using the TUFTestFixtureNestedDelegated fixture
            'level_1_target.txt' => [
                'TUFTestFixtureNestedDelegated',
                'level_1_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => null,
                    'level_3' => null,
                ],
            ],
            'level_1_2_target.txt' => [
                'TUFTestFixtureNestedDelegated',
                'level_1_2_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => null,
                    'level_3' => null,
                ],
            ],
            'level_1_2_terminating_findable.txt' => [
                'TUFTestFixtureNestedDelegated',
                'level_1_2_terminating_findable.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => 1,
                    'level_3' => null,
                ],
            ],
            'level_1_2_3_below_non_terminating_target.txt' => [
                'TUFTestFixtureNestedDelegated',
                'level_1_2_3_below_non_terminating_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => null,
                    'level_3' => 1,
                ],
            ],
            // Roles delegated from a terminating role are evaluated.
            // See § 5.6.7.2.1 and 5.6.7.2.2.
            'level_1_2_terminating_3_target.txt' => [
                'TUFTestFixtureNestedDelegated',
                'level_1_2_terminating_3_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => 1,
                    'level_3' => null,
                    'level_3_below_terminated' => 1,
                ],
            ],
            // A terminating role only has an effect if the target path matches
            // the role, otherwise the role is not evaluated.
            // Roles after terminating delegation where the target path does match not
            // the terminating role are not evaluated.
            // See § 5.6.7.2.1 and 5.6.7.2.2.
            'level_1_2a_terminating_plus_1_more_findable.txt' => [
                'TUFTestFixtureNestedDelegated',
                'level_1_2a_terminating_plus_1_more_findable.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => null,
                    'level_2_terminating' => 1,
                    'level_3' => 1,
                    'level_3_below_terminated' => 1,
                ],
            ],
            // Test cases using the 'TUFTestFixtureTerminatingDelegation' fixture set.
            'TUFTestFixtureTerminatingDelegation targets.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'targets.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => null,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixtureTerminatingDelegation a.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixtureTerminatingDelegation b.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixtureTerminatingDelegation c.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'c.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixtureTerminatingDelegation d.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'd.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            // Test cases using the 'TUFTestFixtureTopLevelTerminating' fixture set.
            'TUFTestFixtureTopLevelTerminating a.txt' => [
                'TUFTestFixtureTopLevelTerminating',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                ],
            ],
            // Test cases using the 'TUFTestFixtureNestedTerminatingNonDelegatingDelegation' fixture set.
            'TUFTestFixtureNestedTerminatingNonDelegatingDelegation a.txt' => [
                'TUFTestFixtureNestedTerminatingNonDelegatingDelegation',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                ],
            ],
            'TUFTestFixtureNestedTerminatingNonDelegatingDelegation b.txt' => [
                'TUFTestFixtureNestedTerminatingNonDelegatingDelegation',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                ],
            ],
            // Test using the TUFTestFixture3LevelDelegation fixture set.
            'TUFTestFixture3LevelDelegation targets.txt' => [
                'TUFTestFixture3LevelDelegation',
                'targets.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => null,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixture3LevelDelegation a.txt' => [
                'TUFTestFixture3LevelDelegation',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixture3LevelDelegation b.txt' => [
                'TUFTestFixture3LevelDelegation',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixture3LevelDelegation c.txt' => [
                'TUFTestFixture3LevelDelegation',
                'c.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixture3LevelDelegation d.txt' => [
                'TUFTestFixture3LevelDelegation',
                'd.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixture3LevelDelegation e.txt' => [
                'TUFTestFixture3LevelDelegation',
                'e.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => 1,
                    'f' => null,
                ],
            ],
            'TUFTestFixture3LevelDelegation f.txt' => [
                'TUFTestFixture3LevelDelegation',
                'f.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => 1,
                    'f' => 1,
                ],
            ],
        ];
    }

    /**
     * Tests for enforcement of maximum number of roles limit.
     */
    public function testMaximumRoles(): void
    {
        $fixturesSet = 'TUFTestFixtureNestedDelegated';
        $fileName = 'level_1_2_terminating_3_target.txt';

        // Ensure the file can found if the maximum role limit is 100.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest($fixturesSet);
        $testFilePath = static::getFixturesRealPath($fixturesSet, "server/targets/$fileName", false);
        $testFileContents = file_get_contents($testFilePath);
        self::assertNotEmpty($testFileContents);
        self::assertSame($testFileContents, $updater->download($fileName)->wait()->getContents());


        // Ensure the file can not found if the maximum role limit is 3.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest($fixturesSet, LimitRolesTestUpdater::class);
        self::expectException(NotFoundException::class);
        self::expectExceptionMessage("Target not found: $fileName");
        $updater->download($fileName)->wait();
    }

    /**
     * Tests that improperly delegated targets will produce exceptions.
     *
     * @param string $fixturesSet
     * @param string $fileName
     * @param array $expectedFileVersions
     *
     * @dataProvider providerDelegationErrors
     */
    public function testDelegationErrors(string $fixturesSet, string $fileName, array $expectedFileVersions): void
    {
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest();
        try {
            $updater->download($fileName)->wait();
        } catch (NotFoundException $exception) {
            self::assertEquals("Target not found: $fileName", $exception->getMessage());
            $this->assertClientFileVersions($expectedFileVersions);
            return;
        }
        self::fail('NotFoundException not thrown.');
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
            // Test using the TUFTestFixtureNestedDelegatedErrors fixture set.
            // 'level_a.txt' is added via the 'unclaimed' role but this role has
            // `paths: ['level_1_*.txt']` which does not match the file name.
            'no path match' => [
                'TUFTestFixtureNestedDelegatedErrors',
                'level_a.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    // The client does not update the 'unclaimed.json' file because
                    // the target file does not match the 'paths' property for the role.
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_1_3_target.txt' is added via the 'level_2' role which has
            // `paths: ['level_1_2_*.txt']`. The 'level_2' role is delegated from the
            // 'unclaimed' role which has `paths: ['level_1_*.txt']`. The file matches
            // for the 'unclaimed' role but does not match for the 'level_2' role.
            'matches parent delegation' => [
                'TUFTestFixtureNestedDelegatedErrors',
                'level_1_3_target.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    'unclaimed' => 3,
                    'level_2' => null,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_2_unfindable.txt' is added via the 'level_2_error' role which has
            // `paths: ['level_2_*.txt']`. The 'level_2_error' role is delegated from the
            // 'unclaimed' role which has `paths: ['level_1_*.txt']`. The file matches
            // for the 'level_2_error' role but does not match for the 'unclaimed' role.
            // No files added via the 'level_2_error' role will be found because its
            // 'paths' property is incompatible with the its parent delegation's
            // 'paths' property.
            'delegated path does not match parent' => [
                'TUFTestFixtureNestedDelegatedErrors',
                'level_2_unfindable.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                // The client does not update the 'unclaimed.json' file because
                // the target file does not match the 'paths' property for the role.
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_1_2_terminating_plus_1_more_unfindable.txt' is added via role
            // 'level_2_after_terminating_match_terminating_path' which is delegated from role at the same level as 'level_2_terminating'
            'delegated path does not match role' => [
                'TUFTestFixtureNestedDelegatedErrors',
                'level_1_2_terminating_plus_1_more_unfindable.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    // The client does update the 'unclaimed.json' file because
                    // the target file does match the 'paths' property for the role.
                    'unclaimed' => 3,
                    'level_2' => 2,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_1_2_terminating_plus_1_more_unfindable.txt' is added via role
            // 'level_2_after_terminating_match_terminating_path' which is delegated from role at the same level as 'level_2_terminating'
            //  but added after 'level_2_terminating'.
            // Because 'level_2_terminating' is a terminating role its own delegations are evaluated but no other
            // delegations are evaluated after it.
            // See § 5.6.7.2.1 and 5.6.7.2.2.
            'delegation is after terminating delegation' => [
                'TUFTestFixtureNestedDelegatedErrors',
                'level_1_2_terminating_plus_1_more_unfindable.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    'unclaimed' => 3,
                    'level_2' => 2,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // Test using the TUFTestFixtureTerminatingDelegation fixture set.
            'TUFTestFixtureTerminatingDelegation e.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'e.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TUFTestFixtureTerminatingDelegation f.txt' => [
                'TUFTestFixtureTerminatingDelegation',
                'f.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            // Test cases using the 'TUFTestFixtureTopLevelTerminating' fixture set.
            'TUFTestFixtureTopLevelTerminating b.txt' => [
                'TUFTestFixtureTopLevelTerminating',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                ],
            ],
            // Test cases using the 'TUFTestFixtureNestedTerminatingNonDelegatingDelegation' fixture set.
            'TUFTestFixtureNestedTerminatingNonDelegatingDelegation c.txt' => [
                'TUFTestFixtureNestedTerminatingNonDelegatingDelegation',
                'c.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                ],
            ],
            'TUFTestFixtureNestedTerminatingNonDelegatingDelegation d.txt' => [
                'TUFTestFixtureNestedTerminatingNonDelegatingDelegation',
                'd.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                ],
            ],
            // Test cases using the 'TUFTestFixture3LevelDelegation' fixture set.
            // A search for non existent target should that matches the paths
            // should search the complete tree.
            'TUFTestFixture3LevelDelegation z.txt' => [
                'TUFTestFixture3LevelDelegation',
                'z.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => 1,
                    'f' => 1,
                ],
            ],
            // A search for non existent target that does match the paths
            // should not search any of the tree.
            'TUFTestFixture3LevelDelegation z.zip' => [
                'TUFTestFixture3LevelDelegation',
                'z.zip',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => null,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);

        $this->assertClientFileVersions($expectedStartVersion);
        $updater = $this->getSystemInTest($fixturesSet);
        $this->assertTrue($updater->refresh($fixturesSet));
        // Confirm the local version are updated to the expected versions.
        $this->assertClientFileVersions($expectedUpdatedVersions);

        // Create another version of the client that only starts with the root.json file.
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        foreach (array_keys($expectedStartVersion) as $role) {
            if ($role !== 'root') {
                // Change the expectation that client will not start with any files other than root.json.
                $expectedStartVersion[$role] = null;
                // Remove all files except root.json.
                unset($this->localRepo["$role.json"]);
            }
        }
        $this->assertClientFileVersions($expectedStartVersion);
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
        $this->assertClientFileVersions($expectedUpdatedVersions);
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
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 4,
                    'unclaimed' => 1,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            [
                'TUFTestFixtureNestedDelegated',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_3' => null,
                ],
            ],
        ], 0);
    }

    /**
     * Asserts that client-side metadata files are at expected versions.
     *
     * @param ?int[] $expectedVersions
     *   The expected versions. The keys are the file names, without the .json
     *   suffix, and the values are the expected version numbers, or NULL if
     *   the file should not be present.
     *
     * @return void
     */
    protected function assertClientFileVersions(array $expectedVersions): void
    {
        foreach ($expectedVersions as $role => $version) {
            if (is_null($version)) {
                $this->assertNull($this->localRepo["$role.json"], "'$role' file is null.");
                return;
            }
            $roleJson = $this->localRepo["$role.json"];
            $this->assertNotNull($roleJson, "'$role.json' found in local repo.");
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientFileVersions(static::getFixtureClientStartVersions('TUFTestFixtureDelegated'));
        $this->testRepo->setRepoFileNestedValue($fileToChange, $keys, $newValue);
        $updater = $this->getSystemInTest($fixturesSet);
        try {
            $updater->refresh();
        } catch (TufException $exception) {
            $this->assertEquals($exception, $expectedException);
            $this->assertClientFileVersions($expectedUpdatedVersions);
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
                '3.root.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdExpception('Signature threshold not met on root'),
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            [
                '4.root.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdExpception('Signature threshold not met on root'),
                [
                    'root' => 3,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            [
                'timestamp.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdExpception('Signature threshold not met on timestamp'),
                [
                    'root' => 4,
                    'timestamp' => null,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            // For snapshot.json files, adding a new key or changing the existing version number
            // will result in a MetadataException indicating that the contents hash does not match
            // the hashes specified in the timestamp.json. This is because timestamp.json in the test
            // fixtures contains the optional 'hashes' metadata for the snapshot.json files, and this
            // is checked before the file signatures and the file version number. The order of checking
            // is specified in § 5.5.
            [
                '4.snapshot.json',
                ['signed', 'newkey'],
                'new value',
                new MetadataException("The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata."),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => null,
                    'targets' => 2,
                ],
            ],
            [
                '4.snapshot.json',
                ['signed', 'version'],
                6,
                new MetadataException("The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata."),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => null,
                    'targets' => 2,
                ],
            ],
            // For targets.json files, adding a new key or changing the existing version number
            // will result in a SignatureThresholdException because currently the test
            // fixtures do not contain hashes for targets.json files in snapshot.json.
            [
                '4.targets.json',
                ['signed', 'newvalue'],
                'value',
                new SignatureThresholdExpception("Signature threshold not met on targets"),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 2,
                ],
            ],
            [
                '4.targets.json',
                ['signed', 'version'],
                6,
                new SignatureThresholdExpception("Signature threshold not met on targets"),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 2,
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientFileVersions(static::getFixtureClientStartVersions($fixturesSet));
        $this->testRepo->removeRepoFile($fileName);
        $updater = $this->getSystemInTest($fixturesSet);
        try {
            $updater->refresh();
        } catch (RepoFileNotFound $exception) {
            $this->assertSame("File $fileName not found.", $exception->getMessage());
            $this->assertClientFileVersions($expectedUpdatedVersions);
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
                    'root' => 4,
                    'timestamp' => null,
                    'snapshot' => null,
                    'targets' => 4,
                ],
            ],
            [
                'TUFTestFixtureDelegated',
                '4.snapshot.json',
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => null,
                    'targets' => 4,
                ],
            ],
            [
                'TUFTestFixtureDelegated',
                '4.targets.json',
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 2,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                'timestamp.json',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                '1.snapshot.json',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            [
                'TUFTestFixtureSimple',
                '1.targets.json',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $updater = $this->getSystemInTest($fixturesSet);
        $this->assertClientFileVersions(static::getFixtureClientStartVersions($fixturesSet));
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);

        $this->assertClientFileVersions(static::getFixtureClientStartVersions($fixturesSet));
        $updater = $this->getSystemInTest();
        // This refresh should succeed.
        $updater->refresh();
        // Put the server-side repo into an invalid state.
        $this->testRepo->removeRepoFile('timestamp.json');
        // The updater is already refreshed, so this will return early, and
        // there should be no changes to the client-side repo.
        $updater->refresh();
        $this->assertClientFileVersions(static::getFixtureClientStartVersions($fixturesSet));
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
        $this->localRepo = $this->memoryStorageFromFixture($fixtureSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixtureSet);
        $startingTargets = $this->localRepo['targets.json'];
        $this->assertClientFileVersions(static::getFixtureClientStartVersions($fixtureSet));
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
                'root' => 2,
                'timestamp' => 2,
                'snapshot' => 2,
                'unsupported_target' => null,
                // We cannot assert the starting versions of 'targets' because it has
                // an unsupported field and would throw an exception when validating.
            ];
            self::assertClientFileVersions($expectedUpdatedVersion);
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
        $this->localRepo = $this->memoryStorageFromFixture($fixturesSet, 'client/metadata/current');
        $this->testRepo = new TestRepo($fixturesSet);
        $this->assertClientFileVersions(static::getFixtureClientStartVersions($fixturesSet));
        $updater = $this->getSystemInTest();
        try {
            // No changes should be made to client repo.
            $this->localRepo->setExceptionOnChange();
            $updater->refresh();
        } catch (TufException $exception) {
            $this->assertEquals($expectedException, $exception);
            $this->assertClientFileVersions($expectedUpdatedVersions);
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
                new RollbackAttackException('Remote timestamp metadata version "$1" is less than previously seen timestamp version "$2"'),
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
        ];
    }
}

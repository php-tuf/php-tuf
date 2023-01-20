<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Client\Updater;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\MetadataException;
use Tuf\Exception\NotFoundException;
use Tuf\Exception\Attack\InvalidHashException;
use Tuf\Exception\Attack\RollbackAttackException;
use Tuf\Exception\Attack\SignatureThresholdException;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Exception\TufException;
use Tuf\Tests\TestHelpers\FixturesTrait;
use Tuf\Tests\TestHelpers\TestClock;
use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * @coversDefaultClass \Tuf\Client\Updater
 */
abstract class UpdaterTest extends TestCase
{
    use FixturesTrait {
        getFixturePath as getFixturePathFromTrait;
    }
    use ProphecyTrait;
    use UtilsTrait;

    /**
     * The client-side metadata storage.
     *
     * @var \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage
     */
    protected $clientStorage;

    /**
     * The server-side storage for metadata and targets.
     *
     * @var \Tuf\Tests\Client\TestRepo
     */
    protected $serverStorage;

    protected const FIXTURE_VARIANT = '';

    protected static function getFixturePath(string $fixtureName, string $subPath = '', bool $isDir = true): string
    {
        return static::getFixturePathFromTrait($fixtureName, static::FIXTURE_VARIANT . "/$subPath", $isDir);
    }

    /**
     * Returns a memory-based updater populated with a specific test fixture.
     *
     * This will initialize $this->serverStorage to fetch server-side data from
     * the fixture, and $this->clientStorage to interact with the fixture's
     * client-side metadata. Both are kept in memory only, and will not cause
     * any permanent side effects.
     *
     * @param string $fixtureName
     *     The name of the fixture to use.
     *
     * @return Updater
     *     The test updater, which uses the 'current' test fixtures in the
     *     client/metadata/current/ directory and a localhost HTTP
     *     mirror.
     */
    protected function getSystemInTest(string $fixtureName, string $updaterClass = Updater::class): Updater
    {
        $mirrors = [
            'mirror1' => [
                'url_prefix' => 'http://localhost:8001',
                'metadata_path' => 'metadata',
                'targets_path' => 'targets',
                'confined_target_dirs' => [],
            ],
        ];

        $this->clientStorage = static::loadFixtureIntoMemory($fixtureName);
        $this->serverStorage = new TestRepo(static::getFixturePath($fixtureName));

        // Remove all '*.[TYPE].json' because they are needed for the tests.
        $fixtureFiles = scandir(static::getFixturePath($fixtureName, 'client/metadata/current'));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/.*\..*\.json/', $fileName)) {
                unset($this->clientStorage[$fileName]);
            }
        }

        $expectedStartVersions = static::getClientStartVersions($fixtureName);
        $this->assertClientFileVersions($expectedStartVersions);

        $updater = new $updaterClass($this->serverStorage, $mirrors, $this->clientStorage);
        // Force the updater to use our test clock so that, like supervillains,
        // we control what time it is.
        $reflector = new \ReflectionObject($updater);
        $property = $reflector->getProperty('clock');
        $property->setAccessible(true);
        $property->setValue($updater, new TestClock());

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
        $fixtureName = 'Simple';
        $updater = $this->getSystemInTest($fixtureName);

        $testFilePath = static::getFixturePath($fixtureName, 'server/targets/testtarget.txt', false);
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
        $this->serverStorage->fileContents['testtarget.txt'] = new FulfilledPromise($stream);
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
        $this->serverStorage->fileContents['testtarget.txt'] = new FulfilledPromise($stream->reveal());
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
        $this->serverStorage->fileContents['testtarget.txt'] = new FulfilledPromise($stream->reveal());
        try {
            $updater->download('testtarget.txt')->wait();
            $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
        } catch (DownloadSizeException $e) {
            $this->assertSame("testtarget.txt exceeded 24 bytes", $e->getMessage());
        }
    }

    /**
     * Tests that TUF transparently verifies targets signed by delegated roles.
     *
     * @param string $fixtureName
     *   The name of the fixture to test with.
     * @param string $target
     *   The target file to download.
     * @param array $expectedFileVersions
     *   The expected client versions after the download.
     *
     * @return void
     * @todo Add test coverage delegated roles that then delegate to other roles in
     *   https://github.com/php-tuf/php-tuf/issues/142
     *
     * @covers ::download
     *
     * § 5.7.3
     *
     * @dataProvider providerVerifiedDelegatedDownload
     *
     */
    public function testVerifiedDelegatedDownload(string $fixtureName, string $target, array $expectedFileVersions): void
    {
        $updater = $this->getSystemInTest($fixtureName);

        $testFilePath = static::getFixturePath($fixtureName, "server/targets/$target", false);
        $testFileContents = file_get_contents($testFilePath);
        self::assertNotEmpty($testFileContents);
        $this->assertSame($testFileContents, $updater->download($target)->wait()->getContents());
        // Ensure that client downloads only the delegated role JSON files that
        // are needed to find the metadata for the target.
        $this->assertClientFileVersions($expectedFileVersions);
    }

    abstract public function providerVerifiedDelegatedDownload(): array;

    /**
     * Tests for enforcement of maximum number of roles limit.
     *
     * § 5.6.7.1
     */
    public function testMaximumRoles(): void
    {
        $fixtureName = 'NestedDelegated';
        $fileName = 'level_1_2_terminating_3_target.txt';

        // Ensure the file can found if the maximum role limit is 100.
        $updater = $this->getSystemInTest($fixtureName);
        $testFilePath = static::getFixturePath($fixtureName, "server/targets/$fileName", false);
        $testFileContents = file_get_contents($testFilePath);
        self::assertNotEmpty($testFileContents);
        self::assertSame($testFileContents, $updater->download($fileName)->wait()->getContents());


        // Ensure the file can not found if the maximum role limit is 3.
        $updater = $this->getSystemInTest($fixtureName, LimitRolesTestUpdater::class);
        self::expectException(NotFoundException::class);
        self::expectExceptionMessage("Target not found: $fileName");
        $updater->download($fileName)->wait();
    }

    /**
     * Tests that improperly delegated targets will produce exceptions.
     *
     * @param string $fixtureName
     * @param string $fileName
     * @param array $expectedFileVersions
     *
     * @dataProvider providerDelegationErrors
     *
     * § 5.6.7.2.1
     * § 5.6.7.2.2
     * § 5.6.7.2.3
     * § 5.7.2
     */
    public function testDelegationErrors(string $fixtureName, string $fileName, array $expectedFileVersions): void
    {
        $updater = $this->getSystemInTest($fixtureName);
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
     * generate_fixtures.NestedDelegatedErrors().
     *
     * @return \string[][]
     */
    abstract public function providerDelegationErrors(): array;

    /**
     * Tests refreshing the repository.
     *
     * @param string $fixtureName
     *   The fixtures set to use.
     * @param array $expectedUpdatedVersions
     *   The expected updated versions.
     *
     * @return void
     *
     * @dataProvider providerRefreshRepository
     */
    public function testRefreshRepository(string $fixtureName, array $expectedUpdatedVersions): void
    {
        $expectedStartVersion = static::getClientStartVersions($fixtureName);

        $updater = $this->getSystemInTest($fixtureName);
        $this->assertTrue($updater->refresh($fixtureName));
        // Confirm the local version are updated to the expected versions.
        // § 5.3.8
        // § 5.4.5
        // § 5.5.7
        // § 5.6.6
        $this->assertClientFileVersions($expectedUpdatedVersions);

        // Create another version of the client that only starts with the root.json file.
        $updater = $this->getSystemInTest($fixtureName);
        foreach (array_keys($expectedStartVersion) as $role) {
            if ($role !== 'root') {
                // Change the expectation that client will not start with any files other than root.json.
                $expectedStartVersion[$role] = null;
                // Remove all files except root.json.
                unset($this->clientStorage["$role.json"]);
            }
        }
        $this->assertClientFileVersions($expectedStartVersion);
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
    abstract public function providerRefreshRepository();

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
        static::assertMetadataVersions($expectedVersions, $this->clientStorage);
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
     * @dataProvider providerExceptionForInvalidMetadata
     */
    public function testExceptionForInvalidMetadata(string $fileToChange, array $keys, $newValue, \Exception $expectedException, array $expectedUpdatedVersions): void
    {
        $fixtureName = 'Delegated';
        $updater = $this->getSystemInTest($fixtureName);
        $this->serverStorage->setRepoFileNestedValue($fileToChange, $keys, $newValue);
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
     * Data provider for testExceptionForInvalidMetadata().
     *
     * @return mixed[]
     *   The test cases for testExceptionForInvalidMetadata().
     */
    abstract public function providerExceptionForInvalidMetadata(): array;

    /**
     * Tests that if a file is missing from the repo an exception is thrown.
     *
     * @param string $fixtureName
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
    public function testFileNotFoundExceptions(string $fixtureName, string $fileName, array $expectedUpdatedVersions): void
    {
        $updater = $this->getSystemInTest($fixtureName);
        // Depending on which file is removed from the server, the update
        // process will error out at various points. That's fine, because we're
        // not trying to complete the refresh.
        $this->serverStorage->removeRepoFile($fileName);
        try {
            $updater->refresh();
            $this->fail('No RepoFileNotFound exception thrown');
        } catch (RepoFileNotFound $exception) {
            // We don't have to do anything with this exception; we just wanted
            // be sure it got thrown. Since the exception is thrown by TestRepo,
            // there's no point in asserting that its message is as expected.
        }
        $this->assertClientFileVersions($expectedUpdatedVersions);
    }

    /**
     * Data provider for testFileNotFoundExceptions().
     *
     * @return mixed[]
     *   The test cases for testFileNotFoundExceptions().
     */
    abstract public function providerFileNotFoundExceptions(): array;


    /**
     * Data provider for testSignatureThresholds().
     *
     * @return mixed[]
     *   The test cases for testSignatureThresholds().
     */
    public function providerTestSignatureThresholds():array
    {
        return [
            ['ThresholdTwo'],
            // § 5.4.2
            ['ThresholdTwoAttack', SignatureThresholdException::class],
        ];
    }

    /**
     * Tests fixtures with signature thresholds greater than 1.
     *
     * @param string $fixtureName
     *   The fixtures set to use.
     * @param string $expectedException
     *   The null or the class name of an expected exception.
     *
     * @return void
     *
     * @dataProvider providerTestSignatureThresholds
     */
    public function testSignatureThresholds(string $fixtureName, string $expectedException = null)
    {
        $updater = $this->getSystemInTest($fixtureName);
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
        $fixtureName = 'Simple';

        $updater = $this->getSystemInTest($fixtureName);
        // This refresh should succeed.
        $updater->refresh();
        // Put the server-side repo into an invalid state.
        $this->serverStorage->removeRepoFile('timestamp.json');
        // The updater is already refreshed, so this will return early, and
        // there should be no changes to the client-side repo.
        $updater->refresh();
        $this->assertClientFileVersions(static::getClientStartVersions($fixtureName));
        // If we force a refresh, the invalid state of the server-side repo will
        // raise an exception.
        $this->expectException(RepoFileNotFound::class);
        $this->expectExceptionMessage('File timestamp.json not found.');
        $updater->refresh(true);
    }

    abstract public function providerUnsupportedRepo(): array;

    /**
     * Tests that an exceptions for an repo with an unsupported field.
     *
     * @dataProvider providerUnsupportedRepo
     */
    public function testUnsupportedRepo(array $expectedUpdatedVersion): void
    {
        $fixtureSet = 'UnsupportedDelegation';
        $updater = $this->getSystemInTest($fixtureSet);
        $startingTargets = $this->clientStorage['targets.json'];
        try {
            $updater->refresh();
        } catch (MetadataException $exception) {
            $expectedMessage = preg_quote("Object(ArrayObject)[signed][delegations][roles][0][path_hash_prefixes]:", '/');
            $expectedMessage .= ".*This field is not supported.";
            self::assertSame(1, preg_match("/$expectedMessage/s", $exception->getMessage()));
            // Assert that the root, timestamp and snapshot metadata files were updated
            // and that the unsupported_target metadata file was not downloaded.
            self::assertClientFileVersions($expectedUpdatedVersion);
            // Ensure that local version of targets has not changed because the
            // server version is invalid.
            self::assertSame($this->clientStorage['targets.json'], $startingTargets);
            return;
        }
        $this->fail('No exception thrown.');
    }

    /**
     * Tests that exceptions are thrown when a repo is in a rollback attack state.
     *
     * @param string $fixtureName
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
    public function testAttackRepoException(string $fixtureName, \Exception $expectedException, array $expectedUpdatedVersions): void
    {
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $updater = $this->getSystemInTest($fixtureName);
        try {
            // No changes should be made to client repo.
            $this->clientStorage->setExceptionOnChange();
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
            // § 5.4.3
            // § 5.4.4
            [
                'AttackRollback',
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

    abstract public function providerKeyRotation(): array;

    /**
     * Tests that the updater correctly handles key rotation (§ 5.3.11)
     *
     * @param string $fixtureName
     *   The name of the fixture to test with.
     * @param array $expectedUpdatedVersions
     *   The expected client-side versions of the TUF metadata after refresh.
     *
     * @dataProvider providerKeyRotation
     *
     * @covers ::hasRotatedKeys
     * @covers ::updateRoot
     */
    public function testKeyRotation(string $fixtureName, array $expectedUpdatedVersions): void
    {
        $updater = $this->getSystemInTest($fixtureName);
        // This will purposefully cause the refresh to fail, immediately after
        // updating the root metadata.
        $this->serverStorage->removeRepoFile('timestamp.json');
        try {
            $updater->refresh();
            $this->fail('Expected a RepoFileNotFound exception, but none was thrown.');
        } catch (RepoFileNotFound $e) {
            // We don't need to do anything with this exception.
        }
        $this->assertClientFileVersions($expectedUpdatedVersions);
    }
}

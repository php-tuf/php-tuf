<?php

namespace Tuf\Tests;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Tuf\CanonicalJsonTrait;
use Tuf\Client\Updater;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\SizeCheckingLoader;
use Tuf\Tests\TestHelpers\DurableStorage\TestStorage;
use Tuf\Tests\TestHelpers\FixturesTrait;
use Tuf\Tests\TestHelpers\TestClock;
use Tuf\Tests\TestHelpers\TestRepository;
use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * Defines a base class for functionally testing the TUF client workflow.
 */
class ClientTestBase extends TestCase implements LoaderInterface
{
    use CanonicalJsonTrait;
    use FixturesTrait;
    use UtilsTrait;

    /**
     * The client-side metadata storage.
     *
     * @var \Tuf\Tests\TestHelpers\DurableStorage\TestStorage
     */
    protected TestStorage $clientStorage;

    /**
     * The server-side TUF repository.
     *
     * @var \Tuf\Tests\TestHelpers\TestRepository
     */
    protected TestRepository $server;

    /**
     * The server-side files, keyed by name.
     *
     * These can be the full file contents as strings, or stream objects which
     * will be loaded as-is.
     *
     * @var string[]|\Psr\Http\Message\StreamInterface[]
     */
    protected array $serverFiles = [];

    /**
     * The $maxBytes arguments passed to ::load(), keyed by locator.
     *
     * @var int[][]
     */
    protected array $maxBytes = [];

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->clientStorage = new TestStorage();
        $this->server = new TestRepository(new SizeCheckingLoader($this));
    }

    /**
     * Returns a TUF client for testing.
     *
     * By default, the client will have no TUF metadata on either the client or
     * server side. You can call other methods to populate those automatically
     * using our fixtures.
     *
     * @param string $updaterClass
     *   (optional) The updater class. Defaults to \Tuf\Client\Updater.
     *
     * @return \Tuf\Client\Updater
     *   The updater.
     *
     * @see ::loadClientAndServerFilesFromFixture()
     * @see ::loadClientFilesFromFixture()
     * @see ::loadServerFilesFromFixture()
     */
    protected function getUpdater(string $updaterClass = Updater::class): Updater
    {
        $updater = new $updaterClass(new SizeCheckingLoader($this), $this->clientStorage);

        // Force the updater to use our test clock so that, like supervillains,
        // we control what time it is.
        $reflector = new \ReflectionObject($updater);
        $property = $reflector->getProperty('clock');
        $property->setAccessible(true);
        $property->setValue($updater, new TestClock());

        $property = $reflector->getProperty('server');
        $property->setAccessible(true);
        $property->setValue($updater, $this->server);

        return $updater;
    }

    /**
     * Loads client- and server-side TUF metadata from a fixture.
     *
     * @param string $fixtureName
     *   The name of the fixture from which to load TUF metadata.
     */
    protected function loadClientAndServerFilesFromFixture(string $fixtureName): void
    {
        $this->loadServerFilesFromFixture($fixtureName);
        $this->loadClientFilesFromFixture($fixtureName);
    }

    /**
     * Loads client-side TUF metadata from a fixture.
     *
     * If this is not called, ::getUpdater() will return an updater that has
     * no TUF metadata stored on the client side.
     *
     * @param string $fixtureName
     *   The name of the fixture from which to load client-side TUF metadata.
     */
    protected function loadClientFilesFromFixture(string $fixtureName): void
    {
        $path = static::getFixturePath($fixtureName, 'client/metadata/current');
        $this->clientStorage = TestStorage::createFromDirectory($path);

        // Remove all '*.[TYPE].json', because they are needed for the tests.
        $fixtureFiles = array_map('basename', scandir($path));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/^[0-9]+\..+\.json$/', $fileName)) {
                // Strip out the file extension.
                $fileName = substr($fileName, 0, -5);
                $this->clientStorage->delete($fileName);
            }
        }

        $versionsFile = dirname($path, 3) . '/client_versions.ini';
        $this->assertFileIsReadable($versionsFile);
        $expectedVersions = parse_ini_file($versionsFile, false, INI_SCANNER_TYPED);
        $this->assertMetadataVersions($expectedVersions, $this->clientStorage);
    }

    /**
     * Loads server-side TUF metadata from a fixture.
     *
     * If this is not called, ::getUpdater() will return an updater that has no
     * TUF metadata on the server side.
     *
     * @param string $fixtureName
     *   The name of the fixture from which to load server-side TUF metadata.
     */
    protected function loadServerFilesFromFixture(string $fixtureName): void
    {
        $basePath = static::getFixturePath($fixtureName);

        // Store all the repo files locally so they can be easily altered.
        // @see self::setRepoFileNestedValue()
        $fixturesPath = "$basePath/server";
        $repoFiles = glob("$fixturesPath/metadata/*.json");
        $targetsPath = "$fixturesPath/targets";
        if (is_dir($targetsPath)) {
            $repoFiles = array_merge($repoFiles, glob("$targetsPath/*"));
        }
        foreach ($repoFiles as $repoFile) {
            $baseName = basename($repoFile);
            if (isset($this->fileContents[$baseName])) {
                throw new \UnexpectedValueException("For testing fixtures target files should not use metadata file names");
            }
            $this->serverFiles[$baseName] = file_get_contents($repoFile);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $locator, int $maxBytes): StreamInterface
    {
        $this->maxBytes[$locator][] = $maxBytes;

        if (!array_key_exists($locator, $this->serverFiles)) {
            throw new RepoFileNotFound("File $locator not found.");
        }
        return Utils::streamFor($this->serverFiles[$locator]);
    }

    /**
     * Sets a nested key in a server-side JSON file.
     *
     * @param string $fileName
     *   The name of the file to change.
     * @param array $keys
     *   The nested array keys of the item.
     * @param mixed $value
     *   The value to set.
     */
    protected function setValueInServerFile(string $fileName, array $keys, mixed $value): void
    {
        $this->assertArrayHasKey($fileName, $this->serverFiles);

        $data = static::decodeJson($this->serverFiles[$fileName]);
        static::nestedChange($keys, $data, $value);
        $this->serverFiles[$fileName] = static::encodeJson($data);
    }
}

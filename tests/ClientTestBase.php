<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\CanonicalJsonTrait;
use Tuf\Client\Updater;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\SizeCheckingLoader;
use Tuf\Tests\Client\TestLoaderTrait;
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
    use TestLoaderTrait;
    use UtilsTrait;

    /**
     * The client-side metadata storage.
     *
     * @var \Tuf\Tests\TestHelpers\DurableStorage\TestStorage
     */
    protected TestStorage $clientStorage;

    /**
     * Alias of $this->fileContents, for clarity.
     *
     * @var array
     */
    protected array $serverFiles;

    /**
     * The server-side TUF repository.
     *
     * @var \Tuf\Tests\TestHelpers\TestRepository
     */
    protected TestRepository $server;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->clientStorage = new TestStorage();
        $this->server = new TestRepository(new SizeCheckingLoader($this));

        // Alias $this->fileContents for clarity.
        $this->serverFiles = &$this->fileContents;
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
        $this->serverFiles = [];

        $basePath = static::getFixturePath($fixtureName);
        $this->populateFromFixture($basePath);
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

<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\SizeCheckingLoader;
use Tuf\Tests\Client\TestLoaderTrait;
use Tuf\Tests\TestHelpers\DurableStorage\TestStorage;
use Tuf\Tests\TestHelpers\FixturesTrait;
use Tuf\Tests\TestHelpers\TestClock;
use Tuf\Tests\TestHelpers\TestRepository;

/**
 * Defines a base class for functionally testing the TUF client workflow.
 */
class ClientTestBase extends TestCase implements LoaderInterface
{
    use FixturesTrait;
    use TestLoaderTrait;

    /**
     * The client-side metadata storage.
     *
     * This is initially empty; use ::loadClientFilesFromFixture() to populate
     * it with the client-side files from a particular fixture.
     *
     * @var \Tuf\Tests\TestHelpers\DurableStorage\TestStorage
     *
     * @see ::loadClientFilesFromFixture()
     * @see ::loadClientAndServerFilesFromFixture()
     */
    protected TestStorage $clientStorage;

    /**
     * The server-side files, as strings or streams.
     *
     * This is initially empty; use ::loadServerFilesFromFixture() to populate
     * it with the server-side files from a particular fixture.
     *
     * @var string[]|\Psr\Http\Message\StreamInterface[]
     *
     * @see ::loadServerFilesFromFixture()
     * @see ::loadClientAndServerFilesFromFixture()
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
     * server side. Use ::loadClientAndServerFilesFromFixture(),
     * ::loadClientFilesFromFixture(), and ::loadServerFilesFromFixture() to
     * populate the client and/or server side with data from our fixtures.
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
     * Populates $this->clientStorage with a fixture's client-side metadata.
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
     * Populates $this->serverFiles with a fixture's server-side metadata.
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
}

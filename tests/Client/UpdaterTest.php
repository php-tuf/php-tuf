<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Metadata\RootMetadata;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

class UpdaterTest extends TestCase
{
    use MemoryStorageLoaderTrait;

    /**
     * Returns a memory-based updater populated with the test fixtures.
     *
     * @param \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage|null $localRepo
     *   The local storage to use or NULL to use default.
     *
     * @return Updater
     *     The test updater, which uses the 'current' test fixtures in the
     *     tufclient/tufrepo/metadata/current/ directory and a localhost HTTP
     *     mirror.
     */
    protected function getSystemInTest(MemoryStorage $localRepo = null) : Updater
    {
        $mirrors = [
            'mirror1' => [
                'url_prefix' => 'http://localhost:8001',
                'metadata_path' => 'metadata',
                'targets_path' => 'targets',
                'confined_target_dirs' => [],
            ],
        ];

        if (!$localRepo) {
            // Use the memory storage used so tests can write without permanent
            // side-effects.
            $localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
        }
        // Remove all '*.[TYPE].json' because they are needed for the tests.
        $fixtureFiles = scandir($this->getFixturesRealPath('tufclient/tufrepo/metadata/current'));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/.*\..*\.json/', $fileName)) {
                unset($localRepo[$fileName]);
            }
        }
        $updater = new Updater('repo1', $mirrors, $localRepo);
        return $updater;
    }

    /**
     * Tests refreshing the repository.
     *
     * @return void
     */
    public function testRefreshRepository() : void
    {
        $updater = $this->getSystemInTest();
        $this->assertTrue($updater->refresh());
    }

    /**
     * Tests refreshing the repository which needs a updated root.
     *
     * @param integer $rootStart
     *   The version of the root file the test should start with.
     *
     * @return void
     *
     * @dataProvider providerUpdatingRoot
     *
     * @throws \Tuf\Exception\MetadataException
     *   Thrown if the metadata is invalid.
     */
    public function testUpdatingRoot(int $rootStart)
    {
        $localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
        // Rollback the root file to version.
        $localRepo['root.json'] = $localRepo["$rootStart.root.json"];
        $this->assertSame($rootStart, RootMetadata::createFromJson($localRepo['root.json'])->getVersion());
        $updater = $this->getSystemInTest($localRepo);
        $this->assertTrue($updater->refresh());
        // Confirm the root was updated to version 3 which is the highest
        // version in the test fixtures.
        $this->assertSame(3, RootMetadata::createFromJson($localRepo['root.json'])->getVersion());
    }

    /**
     * Data provider for testUpdatingRoot().
     *
     * @return \int[][]
     *   The test arguments.
     */
    public function providerUpdatingRoot()
    {
        return [
            [1],
            [2],
            [3],
        ];
    }

    /**
     * Tests that an error is thrown on an updated root.
     *
     * @return void
     *
     * @todo Remove this test when functionality is added.
     */
    public function testUpdatedRootError() : void
    {
    }
}

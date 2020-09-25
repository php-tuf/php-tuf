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

        // Use the memory storage used so tests can write without permanent
        // side-effects.
        if (!$localRepo) {
            $localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
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
     * @return void
     */
    public function testUpdatingRoot()
    {
        $localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
        // Rollback the root file to version 1.
        $localRepo['root.json'] = $localRepo['1.root.json'];
        $this->assertSame(1, RootMetadata::createFromJson($localRepo['root.json'])->getVersion());
        $updater = $this->getSystemInTest($localRepo);
        $this->assertTrue($updater->refresh());
        // Confirm the root was updated.
        $this->assertSame(3, RootMetadata::createFromJson($localRepo['root.json'])->getVersion());
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

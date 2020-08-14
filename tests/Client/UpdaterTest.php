<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\JsonNormalizer;
use Tuf\Tests\DurableStorage\MemoryStorage;

class UpdaterTest extends TestCase
{

    /**
     * @var \Tuf\Tests\DurableStorage\MemoryStorage
     */
    protected $localRepo;

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

        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = self::populateMemoryStorageFromFixtures('tufclient/tufrepo/metadata/current');
        $updater = new Updater('repo1', $mirrors, $this->localRepo);
        return $updater;
    }

    /**
     * Uses test fixtures at a given path to populate a memory storage backend.
     *
     * @param string $path
     *     The relative path (within the fixtures directory) for the client
     *     data.
     *
     * @return MemoryStorage
     *     Memory storage containing the test client data.
     *
     * @throws \RuntimeException
     *     Thrown if the relative path is invalid.
     */
    private static function populateMemoryStorageFromFixtures(string $path) : MemoryStorage
    {
        $realpath = realpath(__DIR__ . "/../../fixtures/$path");
        if ($realpath === false || !is_dir($realpath)) {
            throw new \RuntimeException("Client repository fixtures directory not found at $path");
        }

        $storage = new MemoryStorage();

        // Loop through and load files in the given path.
        $fsIterator = new \FilesystemIterator(
            $realpath,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME
        );
        foreach ($fsIterator as $filename => $info) {
            // Only load JSON files.
            /** @var $info \SplFileInfo */
            if ($info->isFile() && preg_match("|\.json$|", $filename)) {
                $storage[$filename] = file_get_contents($info->getRealPath());
            }
        }

        return $storage;
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

    public function testRollbackAttach() : void
    {
        $updater = $this->getSystemInTest();
        $timestamp = json_decode($this->localRepo['timestamp.json'], true);
        $this->localRepo['timestamp.json'] = JsonNormalizer::asNormalizedJson($timestamp);
        $this->assertTrue($updater->refresh());


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

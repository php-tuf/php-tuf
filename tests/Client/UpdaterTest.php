<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Tests\DurableStorage\InMemoryBackend;

class UpdaterTest extends TestCase
{
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
        $localRepo = $this->populateMemoryStorageFromFixtures('tufclient/tufrepo/metadata/current');
        $updater = new Updater('repo1', $mirrors, $localRepo);
        return $updater;
    }

    /**
     * Uses test fixtures at a given path to populate a memory storage backend.
     *
     * @param string $path
     *     The relative path (within the fixtures directory) for the client
     *     data.
     *
     * @return InMemoryBackend
     *     Memory storage containing the test client data.
     *
     * @throws \RuntimeException
     *     Thrown if the relative path is invalid.
     */
    public function populateMemoryStorageFromFixtures(string $path) : InMemoryBackend
    {
        $realpath = realpath(__DIR__ . "/../../fixtures/$path");
        if ($realpath === false || !is_dir($realpath)) {
            throw new \RuntimeException("Client repository fixtures directory not found at $path");
        }

        $storage = new InMemoryBackend();

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

    public function testUpdateSuccessful()
    {
        $mirrors = [
            'mirror1' => [
                'url_prefix' => 'http://localhost:8001',
                'metadata_path' => 'metadata',
                'targets_path' => 'targets',
                'confined_target_dirs' => [],
            ],
        ];
        $updater = $this->getSystemInTest();
        $fixtureTarget = 'testtarget.txt';
        $targetStream = fopen('data://text/plain,' . 'Test File', 'r');
        $this->assertTrue($updater->validateTarget($fixtureTarget, $targetStream));
    }

  /**
   * Tests that an error is thrown on an updated root.
   *
   * @todo Remove this test when functionality is added.
   */
    public function testUpdatedRootError()
    {
    }
}

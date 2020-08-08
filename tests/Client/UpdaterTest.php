<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Tests\DurableStorage\InMemoryBackend;

class UpdaterTest extends TestCase
{
    /**
     * @return Updater
     */
    protected function getSystemInTest()
    {
        $mirrors = [
            'mirror1' => [
                'url_prefix' => 'http://localhost:8001',
                'metadata_path' => 'metadata',
                'targets_path' => 'targets',
                'confined_target_dirs' => [],
            ],
        ];

        // Memory storage used so tests can write without permanent side-effects.
        $localRepo = $this->populateMemoryStorageFromFixtures('tufclient/tufrepo/metadata/current');
        $updater = new Updater('repo1', $mirrors, $localRepo);
        return $updater;
    }

    public function populateMemoryStorageFromFixtures($path)
    {
        $realpath = realpath(__DIR__ . "/../../fixtures/$path");
        if ($realpath === false || !is_dir($realpath)) {
            throw new \RuntimeException("Client repository fixtures directory not found at $path");
        }

        $storage = new InMemoryBackend();

        $iter = new \FilesystemIterator(
            $realpath,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME
        );
        foreach ($iter as $filename => $info) {
            /**
             * @var $info \SplFileInfo
             */
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

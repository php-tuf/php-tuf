<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;

class UpdaterTest extends TestCase
{

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
        $updater = new Updater('repo1', $mirrors);
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

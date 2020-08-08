<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;

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
        $updater = new Updater('repo1', $mirrors);
        return $updater;
    }

    public function testRefreshRepository()
    {
        $updater = $this->getSystemInTest();
        $this->assertTrue($updater->refresh());
    }

  /**
   * Tests that an error is thrown on an updated root.
   *
   * @todo Remove this test when updating root functionality is added.
   */
    public function testErrorOnUpdatedRoot()
    {
    }

    /*
    public function testValidateTarget()
    {
        $updater = $this->getSystemInTest();
        $fixture_target = 'testtarget.txt';
        $target_stream = fopen('data://text/plain,' . 'Test File', 'r');
        $this->assertTrue($updater->validateTarget($fixture_target, $target_stream));
    }
    */
}

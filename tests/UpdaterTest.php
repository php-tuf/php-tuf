<?php


use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Tests\Helpers\WebserverRunner;

class UpdaterTest extends TestCase {

  public function testUpdateSuccessful() {
    $mirrors = array(
      'mirror1' => array(
        'url_prefix' => 'http://localhost:8001',
        'metadata_path' => 'metadata',
        'targets_path' => 'targets',
        'confined_target_dirs' => array(),
      ),
    );
    $updater = new Updater('repo1', $mirrors);
    $fixture_target = 'testtarget.txt';
    $target_stream = fopen('data://text/plain,' . 'Test File', 'r');
    $this->assertTrue($updater->validateTarget($fixture_target, $target_stream));


  }

  /**
   * Tests that an error is thrown on an updated root.
   *
   * @todo Remove this test when functionality is added.
   */
  public function testUpdatedRootError() {

  }

}
<?php


namespace Tuf\Tests\Helpers;


class WebserverRunner
{
  /**
   * @var resource $h_server_proc
   */
  protected $h_server_proc;

  // Start a development webserver on :8080.
  public function setUpBeforeClass($docroot = "fixtures/tufrepo", $port = 8001) {
    $devnull = fopen('/dev/null', 'r+');
    $foo = array();

    $h_server_proc = proc_open(
      sprintf('exec /usr/bin/env php -S localhost:%d -d error_log=/tmp/php.log -t %s', $port, $docroot),
      array(0 => $devnull, 1 => $devnull, 2 => $devnull),
      $foo
    );
    $this->h_server_proc = $h_server_proc;

    // Wait for the development server to start listening.
    $attempts = 0;
    $url = "http://localhost:$port/";
    $maxAttempts = 10000;
    while ($attempts < 10000) {
      usleep(1000);
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      if (curl_exec($ch) !== false) {
        curl_close($ch);
        break;
      }
      curl_close($ch);
      $attempts++;
    }
    if ($attempts >= $maxAttempts) {
      $this->tearDownAfterClass();
      throw new \RuntimeException("Test webserver could not be contacted.");
    }
  }

  // Stop the development webserver.
  public function tearDownAfterClass() {
    proc_terminate($this->h_server_proc, SIGTERM);
    proc_close($this->h_server_proc);
    $this->h_server_proc = FALSE;
  }
}
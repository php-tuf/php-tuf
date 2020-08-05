<?php


use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Tests\Helpers\WebserverRunner;

class HelloWorldTest extends TestCase
{
  static $webserver;

  public static function setUpBeforeClass() : void
  {
    self::$webserver = new WebserverRunner();
    self::$webserver->setUpBeforeClass("fixtures/tufrepo", 8001);
  }
  public static function tearDownAfterClass() : void
  {
    self::$webserver->tearDownAfterClass();
  }


  public function testIsHello() {
    $u = new Updater();
    $this->assertEquals('hello', $u->sayHello());
  }
}
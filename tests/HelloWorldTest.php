<?php


use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;

class HelloWorldTest extends TestCase
{
  public function testIsHello() {
    $u = new Updater();
    $this->assertEquals('hello', $u->sayHello());
  }
}
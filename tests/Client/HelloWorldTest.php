<?php

namespace Tuf\Client;

use PHPUnit\Framework\TestCase;

/**
 * Class HelloWorldTest
 */
class HelloWorldTest extends TestCase
{

  /**
   * Test salutation.
   */
    public function testIsHello()
    {
        $u = new Updater();
        $this->assertEquals('hello', $u->sayHello());
    }
}

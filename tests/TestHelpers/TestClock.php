<?php

namespace Tuf\Tests\TestHelpers;

use Tuf\Helper\Clock;

/**
 * A test clock class that uses the time from the test fixtures as starting time.
 */
class TestClock extends Clock
{
    private $time = null;

    public function __construct()
    {
        // Use the same timestamp as used in generate_fixtures.py to create
        // fixtures
        $this->time = 1577836800;
    }

    public function getCurrentTime(): int
    {
        $this->time+= 5;
        return $this->time;
    }
}

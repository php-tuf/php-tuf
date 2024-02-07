<?php

namespace Tuf\Tests;

use Tuf\Tests\FixtureBuilder\Fixture;

class FixtureGenerator
{
    public static function generateAll(): void
    {
        self::attackRollback();
    }

    private static function attackRollback(): void
    {
        $dir = realpath(__DIR__ . '/../fixtures') . '/php/AttackRollback/consistent';

        $fixture = new Fixture($dir);
        $fixture->root->consistentSnapshot = true;
        $fixture->createAndSignTarget('testtarget.txt');
        $fixture->publish(true);
    }
}

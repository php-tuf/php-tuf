<?php

namespace Tuf\Tests;

use Tuf\Tests\FixtureBuilder\Fixture;
use Tuf\Tests\FixtureBuilder\Key;

class FixtureGenerator
{
    private static string $baseDir;

    public static function generateAll(): void
    {
        self::$baseDir = realpath(__DIR__ . '/../fixtures');

        self::attackRollback();
        self::delegated();
    }

    private static function attackRollback(): void
    {
        $dir = self::$baseDir . '/php/AttackRollback/consistent';

        $fixture = new Fixture($dir);
        $fixture->root->consistentSnapshot = true;
        $fixture->createTarget('testtarget.txt');
        $fixture->publish();
        // Because the client will now have newer information than the server,
        // TUF will consider this a rollback attack.
        $fixture->createTarget('testtarget2.txt');
        $fixture->writeClient();
    }

    private static function delegated(): void
    {
        $dir = self::$baseDir . '/php/Delegated/consistent';

        $fixture = new Fixture($dir);
        $fixture->root->consistentSnapshot = true;
        $fixture->createTarget('testtarget.txt');
        $fixture->publish();

        $unclaimed = $fixture->delegate('targets', 'unclaimed');
        $unclaimed->paths = ['level_1_*.txt'];
        $fixture->createTarget('level_1_target.txt', 'unclaimed');
        $fixture->publish();

        // From this point on, we don't write the client. This allows us to test
        // that the client is able to pick up changes from the server.
        $fixture->targets['targets']->addKey(new Key);
        $fixture->snapshot->addKey(new Key);
        $fixture->writeServer();
        $fixture->newVersion();
        $fixture->targets['targets']->revokeKey(-1);
        $fixture->snapshot->revokeKey(-1);
        $fixture->writeServer();
        $fixture->newVersion();
    }
}

<?php

namespace Tuf\Tests;

use Tuf\Tests\FixtureBuilder\Fixture;
use Tuf\Tests\FixtureBuilder\Key;

class FixtureGenerator
{
    public static function generateAll(): void
    {
        foreach ([true, false] as $consistent) {
            self::attackRollback($consistent);
            self::delegated($consistent);
            self::nestedDelegated($consistent);
            self::nestedDelegatedErrors($consistent);
            self::nestedTerminatingNonDelegatingDelegation($consistent);
            self::publishedTwice($consistent, null);
            self::publishedTwice($consistent, 'snapshot');
            self::publishedTwice($consistent, 'timestamp');
            self::simple($consistent);
            self::targetsLengthNoSnapshotLength($consistent);
            self::terminatingDelegation($consistent);
            self::threeLevelDelegation($consistent);
            self::thresholdTwo($consistent);
        }
    }

    private static function init(string $name, bool $consistent): Fixture
    {
        $dir = sprintf(
          '%s/php/%s/%s',
          realpath(__DIR__ . '/../fixtures'),
          $name,
          $consistent ? 'consistent' : 'inconsistent',
        );
        $fixture = new Fixture($dir);
        $fixture->root->consistentSnapshot = $consistent;
        return $fixture;
    }

    private static function attackRollback(bool $consistent): void
    {
        $fixture = self::init('AttackRollback', $consistent);

        $fixture->createTarget('testtarget.txt');
        $fixture->publish();
        // Because the client will now have newer information than the server,
        // TUF will consider this a rollback attack.
        $fixture->createTarget('testtarget2.txt');
        $fixture->writeClient();
    }

    private static function delegated(bool $consistent): void
    {
        $fixture = self::init('Delegated', $consistent);
        $fixture->createTarget('testtarget.txt');
        $fixture->publish();

        $unclaimed = $fixture->delegate('targets', 'unclaimed');
        $unclaimed->paths = ['level_1_*.txt'];
        $fixture->createTarget('level_1_target.txt', $unclaimed);
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

    private static function nestedDelegated(bool $consistent): void
    {
        $fixture = self::init('NestedDelegated', $consistent);

        $fixture->createTarget('testtarget.txt');
        $fixture->publish();

        $unclaimed = $fixture->delegate('targets', 'unclaimed');
        $unclaimed->paths = ['level_1_*.txt'];
        $fixture->createTarget('level_1_target.txt', 'unclaimed');
        $fixture->publish();

        $fixture->targets['targets']->addKey(new Key);
        $fixture->snapshot->addKey(new Key);
        $fixture->writeServer();
        $fixture->newVersion();

        $fixture->targets['targets']->revokeKey(-1);
        $fixture->snapshot->revokeKey(-1);
        $fixture->writeServer();
        $fixture->newVersion();

        # Delegate from level_1_delegation to level_2
        $level_2 = $fixture->delegate($unclaimed, 'level_2');
        $level_2->paths = ['level_1_2_*.txt'];
        $fixture->createTarget('level_1_2_target.txt', $level_2);

        # Create a terminating delegation
        $level_2_terminating = $fixture->delegate($unclaimed, 'level_2_terminating');
        $level_2_terminating->terminating = true;
        $level_2_terminating->paths = ['level_1_2_terminating_*.txt'];
        $fixture->createTarget('level_1_2_terminating_findable.txt', $level_2_terminating);

        # Create a delegation under non-terminating 'level_2' delegation.
        $level_3 = $fixture->delegate($level_2, 'level_3');
        $level_3->paths = ['level_1_2_3_*.txt'];
        $fixture->createTarget('level_1_2_3_below_non_terminating_target.txt', $level_3);

        # Add a delegation below the 'level_2_terminating' role.
        # Delegations from a terminating role are evaluated but delegations after a terminating delegation
        # are not.
        # See NestedDelegatedErrors
        $level_3_below_terminated = $fixture->delegate($level_2_terminating, 'level_3_below_terminated');
        $level_3_below_terminated->paths = ['level_1_2_terminating_3_*.txt'];
        $fixture->createTarget('level_1_2_terminating_3_target.txt', $level_3_below_terminated);

        # Add a delegation after level_2_terminating, but the path does not match level_2_terminating,
        # which WILL be evaluated.
        $level_2_after_terminating_not_match_terminating_path = $fixture->delegate('unclaimed', 'level_2_after_terminating_not_match_terminating_path');
        $level_2_after_terminating_not_match_terminating_path->paths = ['level_1_2a_terminating_plus_1_more_*.txt'];
        $fixture->createTarget('level_1_2a_terminating_plus_1_more_findable.txt', $level_2_after_terminating_not_match_terminating_path);

        $fixture->writeServer();
    }

    private static function nestedDelegatedErrors(bool $consistent): void
    {
        $fixture = self::init('NestedDelegatedErrors', $consistent);

        $fixture->createTarget('testtarget.txt');
        $fixture->publish();

        $unclaimed = $fixture->delegate('targets', 'unclaimed', [
          'paths' => ['level_1_*.txt'],
        ]);
        $fixture->createTarget('level_1_target.txt', $unclaimed);
        $fixture->publish();

        $fixture->targets['targets']->addKey(new Key);
        $fixture->snapshot->addKey(new Key);
        $fixture->writeServer();
        $fixture->newVersion();

        $fixture->targets['targets']->revokeKey(-1);
        $fixture->snapshot->revokeKey(-1);
        $fixture->writeServer();
        $fixture->newVersion();

        # Delegate from level_1_delegation to level_2
        $level_2 = $fixture->delegate($unclaimed, 'level_2', [
          'paths' => ['level_1_2_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_target.txt', $level_2);

        # Create a terminating delegation
        $level_2_terminating = $fixture->delegate($unclaimed, 'level_2_terminating', [
          'paths' => [
            'level_1_2_terminating_*.txt',
          ],
          'terminating' => true,
        ]);
        $fixture->createTarget('level_1_2_terminating_findable.txt', $level_2_terminating);

        # Create a delegation under non-terminating 'level_2' delegation.
        $fixture->delegate($level_2, 'level_3', [
          'paths' => ['level_1_2_3_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_3_below_non_terminating_target.txt', 'level_3');

        # Add a delegation below the 'level_2_terminating' role.
        # Delegations from a terminating role are evaluated but delegations after a terminating delegation
        # are not.
        # See NestedDelegatedErrors
        $fixture->delegate($level_2_terminating, 'level_3_below_terminated', [
          'paths' => ['level_1_2_terminating_3_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_terminating_3_target.txt', 'level_3_below_terminated');

        $fixture->writeServer();
        $fixture->newVersion();

        # Add a target that does not match the path for the delegation.
        $fixture->createTarget('level_a.txt', $unclaimed);
        # Add a target that matches the path parent delegation but not the current delegation.
        $fixture->createTarget('level_1_3_target.txt', $level_2);
        # Add a target that does not match the delegation's paths.
        $fixture->createTarget('level_2_unfindable.txt', $level_2_terminating);

        # Add a delegation after level_2_terminating which will not be evaluated.
        $fixture->delegate($unclaimed, 'level_2_terminating_match_terminating_path', [
          'paths' => [
            'level_1_2_terminating_plus_1_more_*.txt',
          ]
        ]);
        $fixture->createTarget('level_1_2_terminating_plus_1_more_unfindable.txt', 'level_2_terminating_match_terminating_path');
        $fixture->writeServer();
        $fixture->newVersion();
    }

    private static function nestedTerminatingNonDelegatingDelegation(bool $consistent): void
    {
        $fixture = self::init('NestedTerminatingNonDelegatingDelegation', $consistent);
        $fixture->publish();

        $fixture->createTarget('targets.txt');
        $fixture->delegate('targets', 'a', [
          'paths' => ['*.txt'],
        ]);
        $fixture->createTarget('a.txt', 'a');
        $fixture->delegate('a', 'b', [
          'paths' => ['*.txt'],
          'terminating' => true,
        ]);
        $fixture->createTarget('b.txt', 'b');
        $fixture->delegate('a', 'c', [
          'paths' => ['*.txt'],
        ]);
        $fixture->createTarget('c.txt', 'c');
        $fixture->delegate('targets', 'd', [
          'paths' => ['*.txt'],
        ]);
        $fixture->createTarget('d.txt', 'd');
        $fixture->writeServer();
        $fixture->newVersion();
    }

    private static function publishedTwice(bool $consistent, ?string $rotatedRole): void
    {
        $name = 'PublishedTwice';
        if ($rotatedRole) {
            $name .= "WithRotatedKeys_$rotatedRole";
        }
        $fixture = self::init($name, $consistent);
        $fixture->publish();
        $fixture->createTarget('test.txt');

        if ($rotatedRole) {
            $fixture->$rotatedRole->addKey(new Key)->revokeKey(0);
        }
        $fixture->writeServer();
        $fixture->newVersion();
    }

    private static function simple(bool $consistent): void
    {
        $fixture = self::init('Simple', $consistent);
        $fixture->createTarget('testtarget.txt');
        $fixture->publish();
    }

    private static function targetsLengthNoSnapshotLength(bool $consistent): void
    {
        $fixture = self::init('TargetsLengthNoSnapshotLength', $consistent);
        $fixture->timestamp->withLength = false;
        $fixture->publish();
        $fixture->writeServer();
        $fixture->newVersion();
    }

    private static function terminatingDelegation(bool $consistent): void
    {
        $fixture = self::init('TerminatingDelegation', $consistent);
        $fixture->publish();
        $fixture->createTarget('targets.txt');
        $properties = [
          'paths' => ['*.txt'],
        ];
        $fixture->delegate('targets', 'a', $properties);
        $fixture->createTarget('a.txt', 'a');
        $fixture->delegate('a', 'b', $properties + [
          'terminating' => true,
        ]);
        $fixture->createTarget('b.txt', 'b');
        $fixture->delegate('b', 'c', $properties);
        $fixture->createTarget('c.txt', 'c');
        $fixture->delegate('b', 'd', $properties);
        $fixture->createTarget('d.txt', 'd');
        $fixture->delegate('a', 'e', $properties);
        $fixture->createTarget('e.txt', 'e');
        $fixture->delegate('targets', 'f', $properties);
        $fixture->createTarget('f.txt', 'f');
        $fixture->writeServer();
        $fixture->newVersion();
    }

    private static function threeLevelDelegation(bool $consistent): void
    {
        $fixture = self::init('ThreeLevelDelegation', $consistent);
        $fixture->publish();
        $fixture->createTarget('targets.txt');
        $properties = [
          'paths' => ['*.txt'],
        ];
        $fixture->delegate('targets', 'a', $properties);
        $fixture->createTarget('a.txt', 'a');
        $fixture->delegate('a', 'b', $properties);
        $fixture->createTarget('b.txt', 'b');
        $fixture->delegate('b', 'c', $properties);
        $fixture->createTarget('c.txt', 'c');
        $fixture->delegate('b', 'd', $properties);
        $fixture->createTarget('d.txt', 'd');
        $fixture->delegate('a', 'e', $properties);
        $fixture->createTarget('e.txt', 'e');
        $fixture->delegate('targets', 'f', $properties);
        $fixture->createTarget('f.txt', 'f');
        $fixture->writeServer();
        $fixture->newVersion();
    }

    private static function thresholdTwo(bool $consistent): void
    {
        $fixture = self::init('ThresholdTwo', $consistent);
        $fixture->timestamp->addKey(new Key);
        $fixture->timestamp->threshold = 2;
        $fixture->publish();
    }
}

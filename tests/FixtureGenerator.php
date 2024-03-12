<?php

namespace Tuf\Tests;

use Tuf\CanonicalJsonTrait;
use Tuf\Tests\FixtureBuilder\Fixture;

class FixtureGenerator
{

    use CanonicalJsonTrait;

    public static function generateAll(): void
    {
        foreach ([true, false] as $consistent) {
            self::delegated($consistent);
            self::nestedDelegated($consistent);
            self::nestedDelegatedErrors($consistent);
            self::nestedTerminatingNonDelegatingDelegation($consistent);
            self::simple($consistent);
            self::targetsLengthNoSnapshotLength($consistent);
            self::terminatingDelegation($consistent);
            self::threeLevelDelegation($consistent);
            self::topLevelLTerminating($consistent);
        }
    }

    private static function init(string $name, bool $consistent): Fixture
    {
        $dir = implode('/', [
            realpath(__DIR__ . '/../fixtures'),
            $name,
            $consistent ? 'consistent' : 'inconsistent',
        ]);
        $fixture = new Fixture($dir);
        $fixture->root->consistentSnapshot = $consistent;
        return $fixture;
    }

    private static function delegated(bool $consistent): void
    {
        $fixture = self::init('Delegated', $consistent);
        $fixture->timestamp->withLength = false;
        $fixture->createTarget('testtarget.txt');
        $fixture->publish(true);

        $unclaimed = $fixture->delegate('targets', 'unclaimed');
        $unclaimed->paths = ['level_1_*.txt'];
        $fixture->createTarget('level_1_target.txt', $unclaimed);
        $fixture->publish(true);

        // From this point on, we don't write the client. This allows us to test
        // that the client is able to pick up changes from the server.
        $fixture->targets['targets']->addKey();
        $fixture->snapshot->addKey();
        $fixture->invalidate();
        $fixture->publish();
        $fixture->targets['targets']->revokeKey(0);
        $fixture->snapshot->revokeKey(0);
        $fixture->invalidate();
        $fixture->publish();
    }

    private static function nestedDelegated(bool $consistent): void
    {
        $fixture = self::init('NestedDelegated', $consistent);

        $fixture->createTarget('testtarget.txt');
        $fixture->publish(true);

        $unclaimed = $fixture->delegate('targets', 'unclaimed');
        $unclaimed->paths = ['level_1_*.txt'];
        $fixture->createTarget('level_1_target.txt', 'unclaimed');
        $fixture->publish(true);

        $fixture->targets['targets']->addKey();
        $fixture->snapshot->addKey();
        $fixture->publish();

        $fixture->targets['targets']->revokeKey(-1);
        $fixture->snapshot->revokeKey(-1);
        $fixture->publish();

        # Delegate from level_1_delegation to level_2
        $level2 = $fixture->delegate($unclaimed, 'level_2');
        $level2->paths = ['level_1_2_*.txt'];
        $fixture->createTarget('level_1_2_target.txt', $level2);

        # Create a terminating delegation
        $level2Terminating = $fixture->delegate($unclaimed, 'level_2_terminating');
        $level2Terminating->terminating = true;
        $level2Terminating->paths = ['level_1_2_terminating_*.txt'];
        $fixture->createTarget('level_1_2_terminating_findable.txt', $level2Terminating);

        # Create a delegation under non-terminating 'level_2' delegation.
        $level3 = $fixture->delegate($level2, 'level_3');
        $level3->paths = ['level_1_2_3_*.txt'];
        $fixture->createTarget('level_1_2_3_below_non_terminating_target.txt', $level3);

        # Add a delegation below the 'level_2_terminating' role.
        # Delegations from a terminating role are evaluated but delegations after a terminating delegation
        # are not.
        # See NestedDelegatedErrors
        $level3BelowTerminated = $fixture->delegate($level2Terminating, 'level_3_below_terminated');
        $level3BelowTerminated->paths = ['level_1_2_terminating_3_*.txt'];
        $fixture->createTarget('level_1_2_terminating_3_target.txt', $level3BelowTerminated);

        # Add a delegation after level_2_terminating, but the path does not match level_2_terminating,
        # which WILL be evaluated.
        $level2AfterTerminatingNotMatchTerminatingPath = $fixture->delegate('unclaimed', 'level_2_after_terminating_not_match_terminating_path');
        $level2AfterTerminatingNotMatchTerminatingPath->paths = ['level_1_2a_terminating_plus_1_more_*.txt'];
        $fixture->createTarget('level_1_2a_terminating_plus_1_more_findable.txt', $level2AfterTerminatingNotMatchTerminatingPath);

        $fixture->publish();
    }

    private static function nestedDelegatedErrors(bool $consistent): void
    {
        $fixture = self::init('NestedDelegatedErrors', $consistent);

        $fixture->createTarget('testtarget.txt');
        $fixture->publish(true);

        $unclaimed = $fixture->delegate('targets', 'unclaimed', [
            'paths' => ['level_1_*.txt'],
        ]);
        $fixture->createTarget('level_1_target.txt', $unclaimed);
        $fixture->publish(true);

        $fixture->targets['targets']->addKey();
        $fixture->snapshot->addKey();
        $fixture->publish();

        $fixture->targets['targets']->revokeKey(-1);
        $fixture->snapshot->revokeKey(-1);
        $fixture->publish();

        # Delegate from level_1_delegation to level_2
        $level2 = $fixture->delegate($unclaimed, 'level_2', [
            'paths' => ['level_1_2_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_target.txt', $level2);

        # Create a terminating delegation
        $level2Terminating = $fixture->delegate($unclaimed, 'level_2_terminating', [
            'paths' => ['level_1_2_terminating_*.txt'],
            'terminating' => true,
        ]);
        $fixture->createTarget('level_1_2_terminating_findable.txt', $level2Terminating);

        # Create a delegation under non-terminating 'level_2' delegation.
        $fixture->delegate($level2, 'level_3', [
            'paths' => ['level_1_2_3_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_3_below_non_terminating_target.txt', 'level_3');

        # Add a delegation below the 'level_2_terminating' role.
        # Delegations from a terminating role are evaluated but delegations after a terminating delegation
        # are not.
        # See NestedDelegatedErrors
        $fixture->delegate($level2Terminating, 'level_3_below_terminated', [
            'paths' => ['level_1_2_terminating_3_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_terminating_3_target.txt', 'level_3_below_terminated');

        $fixture->publish();

        # Add a target that does not match the path for the delegation.
        $fixture->createTarget('level_a.txt', $unclaimed);
        # Add a target that matches the path parent delegation but not the current delegation.
        $fixture->createTarget('level_1_3_target.txt', $level2);
        # Add a target that does not match the delegation's paths.
        $fixture->createTarget('level_2_unfindable.txt', $level2Terminating);

        # Add a delegation after level_2_terminating which will not be evaluated.
        $fixture->delegate($unclaimed, 'level_2_terminating_match_terminating_path', [
            'paths' => ['level_1_2_terminating_plus_1_more_*.txt'],
        ]);
        $fixture->createTarget('level_1_2_terminating_plus_1_more_unfindable.txt', 'level_2_terminating_match_terminating_path');
        $fixture->publish();
    }

    private static function nestedTerminatingNonDelegatingDelegation(bool $consistent): void
    {
        $fixture = self::init('NestedTerminatingNonDelegatingDelegation', $consistent);
        $fixture->publish(true);

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
        $fixture->publish();
    }

    private static function simple(bool $consistent): void
    {
        $fixture = self::init('Simple', $consistent);
        $fixture->snapshot->withHashes = true;
        $fixture->timestamp->withLength = true;
        $fixture->createTarget('testtarget.txt');
        $fixture->publish(true);
    }

    private static function targetsLengthNoSnapshotLength(bool $consistent): void
    {
        $fixture = self::init('TargetsLengthNoSnapshotLength', $consistent);
        $fixture->timestamp->withLength = false;
        $fixture->snapshot->withLength = true;
        $fixture->publish(true);
        $fixture->publish();
    }

    private static function terminatingDelegation(bool $consistent): void
    {
        $fixture = self::init('TerminatingDelegation', $consistent);
        $fixture->publish(true);
        $fixture->createTarget('targets.txt');
        $properties = [
            'paths' => ['*.txt'],
        ];
        $fixture->delegate('targets', 'a', $properties);
        $fixture->createTarget('a.txt', 'a');
        $fixture->delegate('a', 'b', $properties + ['terminating' => true]);
        $fixture->createTarget('b.txt', 'b');
        $fixture->delegate('b', 'c', $properties);
        $fixture->createTarget('c.txt', 'c');
        $fixture->delegate('b', 'd', $properties);
        $fixture->createTarget('d.txt', 'd');
        $fixture->delegate('a', 'e', $properties);
        $fixture->createTarget('e.txt', 'e');
        $fixture->delegate('targets', 'f', $properties);
        $fixture->createTarget('f.txt', 'f');
        $fixture->publish();
    }

    private static function threeLevelDelegation(bool $consistent): void
    {
        $fixture = self::init('ThreeLevelDelegation', $consistent);
        $fixture->publish(true);
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
        $fixture->publish();
    }

    private static function topLevelLTerminating(bool $consistent): void
    {
        $fixture = self::init('TopLevelTerminating', $consistent);
        $fixture->publish(true);
        $fixture->createTarget('targets.txt');
        $fixture->delegate('targets', 'a', [
            'paths' => ["*.txt"],
            'terminating' => true,
        ]);
        $fixture->createTarget('a.txt', 'a');
        $fixture->delegate('targets', 'b', [
            'paths' => ["*.txt"],
        ]);
        $fixture->createTarget('b.txt', 'b');
        $fixture->publish();
    }
}

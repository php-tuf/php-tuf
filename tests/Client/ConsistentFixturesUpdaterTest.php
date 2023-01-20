<?php

namespace Tuf\Tests\Client;

use Tuf\Exception\Attack\SignatureThresholdException;
use Tuf\Exception\MetadataException;

/**
 * Runs UpdaterTest's test cases on the fixtures with consistent snapshots.
 */
class ConsistentFixturesUpdaterTest extends UpdaterTest
{
    /**
     * {@inheritdoc}
     */
    protected const FIXTURE_VARIANT = 'consistent';

    /**
     * {@inheritdoc}
     */
    public function providerRefreshRepository(): array
    {
        return static::getKeyedArray([
            [
                'Delegated',
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 4,
                    'unclaimed' => 1,
                ],
            ],
            [
                'Simple',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            [
                'NestedDelegated',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_3' => null,
                ],
            ],
        ], 0);
    }

    /**
     * {@inheritdoc}
     */
    public function providerExceptionForInvalidMetadata(): array
    {
        return static::getKeyedArray([
            [
                // § 5.3.4
                '3.root.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdException('Signature threshold not met on root'),
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            [
                // § 5.3.4
                '4.root.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdException('Signature threshold not met on root'),
                [
                    'root' => 3,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            [
                // § 5.3.11
                // § 5.4.2
                'timestamp.json',
                ['signed', 'newkey'],
                'new value',
                new SignatureThresholdException('Signature threshold not met on timestamp'),
                [
                    'root' => 4,
                    'timestamp' => null,
                    'snapshot' => 2,
                    'targets' => 2,
                ],
            ],
            // For snapshot.json files, adding a new key or changing the existing version number
            // will result in a MetadataException indicating that the contents hash does not match
            // the hashes specified in the timestamp.json. This is because timestamp.json in the test
            // fixtures contains the optional 'hashes' metadata for the snapshot.json files, and this
            // is checked before the file signatures and the file version number. The order of checking
            // is specified in § 5.5.
            // § 5.3.11
            // § 5.5.2
            [
                '4.snapshot.json',
                ['signed', 'newkey'],
                'new value',
                new MetadataException("The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata."),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => null,
                    'targets' => 2,
                ],
            ],
            // § 5.3.11
            // § 5.5.2
            [
                '4.snapshot.json',
                ['signed', 'version'],
                6,
                new MetadataException("The 'snapshot' contents does not match hash 'sha256' specified in the 'timestamp' metadata."),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => null,
                    'targets' => 2,
                ],
            ],
            // For targets.json files, adding a new key or changing the existing version number
            // will result in a SignatureThresholdException because currently the test
            // fixtures do not contain hashes for targets.json files in snapshot.json.
            // § 5.6.3
            [
                '4.targets.json',
                ['signed', 'newvalue'],
                'value',
                new SignatureThresholdException("Signature threshold not met on targets"),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 2,
                ],
            ],
            // § 5.6.3
            [
                '4.targets.json',
                ['signed', 'version'],
                6,
                new SignatureThresholdException("Signature threshold not met on targets"),
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 2,
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function providerVerifiedDelegatedDownload(): array
    {
        return [
            // Test cases using the NestedDelegated fixture
            'level_1_target.txt' => [
                'NestedDelegated',
                'level_1_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => null,
                    'level_3' => null,
                ],
            ],
            'level_1_2_target.txt' => [
                'NestedDelegated',
                'level_1_2_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => null,
                    'level_3' => null,
                ],
            ],
            'level_1_2_terminating_findable.txt' => [
                'NestedDelegated',
                'level_1_2_terminating_findable.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => 1,
                    'level_3' => null,
                ],
            ],
            'level_1_2_3_below_non_terminating_target.txt' => [
                'NestedDelegated',
                'level_1_2_3_below_non_terminating_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => null,
                    'level_3' => 1,
                ],
            ],
            // Roles delegated from a terminating role are evaluated.
            // See § 5.6.7.2.1 and 5.6.7.2.2.
            'level_1_2_terminating_3_target.txt' => [
                'NestedDelegated',
                'level_1_2_terminating_3_target.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => 1,
                    'level_2_terminating' => 1,
                    'level_3' => null,
                    'level_3_below_terminated' => 1,
                ],
            ],
            // A terminating role only has an effect if the target path matches
            // the role, otherwise the role is not evaluated.
            // Roles after (i.e., next to) a terminating delegation, where the
            // target path does match not the terminating role, are not
            // evaluated.
            // See § 5.6.7.2.1 and 5.6.7.2.2.
            'level_1_2a_terminating_plus_1_more_findable.txt' => [
                'NestedDelegated',
                'level_1_2a_terminating_plus_1_more_findable.txt',
                [
                    'root' => 5,
                    'timestamp' => 5,
                    'snapshot' => 5,
                    'targets' => 5,
                    'unclaimed' => 2,
                    'level_2' => null,
                    'level_2_terminating' => 1,
                    'level_3' => 1,
                    'level_3_below_terminated' => 1,
                ],
            ],
            // Test cases using the 'TerminatingDelegation' fixture set.
            'TerminatingDelegation targets.txt' => [
                'TerminatingDelegation',
                'targets.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => null,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TerminatingDelegation a.txt' => [
                'TerminatingDelegation',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TerminatingDelegation b.txt' => [
                'TerminatingDelegation',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TerminatingDelegation c.txt' => [
                'TerminatingDelegation',
                'c.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TerminatingDelegation d.txt' => [
                'TerminatingDelegation',
                'd.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            // Test cases using the 'TopLevelTerminating' fixture set.
            'TopLevelTerminating a.txt' => [
                'TopLevelTerminating',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                ],
            ],
            // Test cases using the 'NestedTerminatingNonDelegatingDelegation' fixture set.
            'NestedTerminatingNonDelegatingDelegation a.txt' => [
                'NestedTerminatingNonDelegatingDelegation',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                ],
            ],
            'NestedTerminatingNonDelegatingDelegation b.txt' => [
                'NestedTerminatingNonDelegatingDelegation',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                ],
            ],
            // Test using the ThreeLevelDelegation fixture set.
            'ThreeLevelDelegation targets.txt' => [
                'ThreeLevelDelegation',
                'targets.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => null,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'ThreeLevelDelegation a.txt' => [
                'ThreeLevelDelegation',
                'a.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'ThreeLevelDelegation b.txt' => [
                'ThreeLevelDelegation',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'ThreeLevelDelegation c.txt' => [
                'ThreeLevelDelegation',
                'c.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'ThreeLevelDelegation d.txt' => [
                'ThreeLevelDelegation',
                'd.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'ThreeLevelDelegation e.txt' => [
                'ThreeLevelDelegation',
                'e.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => 1,
                    'f' => null,
                ],
            ],
            'ThreeLevelDelegation f.txt' => [
                'ThreeLevelDelegation',
                'f.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => 1,
                    'f' => 1,
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function providerDelegationErrors(): array
    {
        return [
            // Test using the NestedDelegatedErrors fixture set.
            // 'level_a.txt' is added via the 'unclaimed' role but this role has
            // `paths: ['level_1_*.txt']` which does not match the file name.
            'no path match' => [
                'NestedDelegatedErrors',
                'level_a.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    // The client does not update the 'unclaimed.json' file because
                    // the target file does not match the 'paths' property for the role.
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_1_3_target.txt' is added via the 'level_2' role which has
            // `paths: ['level_1_2_*.txt']`. The 'level_2' role is delegated from the
            // 'unclaimed' role which has `paths: ['level_1_*.txt']`. The file matches
            // for the 'unclaimed' role but does not match for the 'level_2' role.
            'matches parent delegation' => [
                'NestedDelegatedErrors',
                'level_1_3_target.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    'unclaimed' => 3,
                    'level_2' => null,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_2_unfindable.txt' is added via the 'level_2_error' role which has
            // `paths: ['level_2_*.txt']`. The 'level_2_error' role is delegated from the
            // 'unclaimed' role which has `paths: ['level_1_*.txt']`. The file matches
            // for the 'level_2_error' role but does not match for the 'unclaimed' role.
            // No files added via the 'level_2_error' role will be found because its
            // 'paths' property is incompatible with the its parent delegation's
            // 'paths' property.
            'delegated path does not match parent' => [
                'NestedDelegatedErrors',
                'level_2_unfindable.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    // The client does not update the 'unclaimed.json' file because
                    // the target file does not match the 'paths' property for the role.
                    'unclaimed' => 1,
                    'level_2' => null,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_1_2_terminating_plus_1_more_unfindable.txt' is added via role
            // 'level_2_after_terminating_match_terminating_path' which is delegated from role at the same level as 'level_2_terminating'
            'delegated path does not match role' => [
                'NestedDelegatedErrors',
                'level_1_2_terminating_plus_1_more_unfindable.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    // The client does update the 'unclaimed.json' file because
                    // the target file does match the 'paths' property for the role.
                    'unclaimed' => 3,
                    'level_2' => 2,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // 'level_1_2_terminating_plus_1_more_unfindable.txt' is added via role
            // 'level_2_after_terminating_match_terminating_path' which is delegated from role at the same level as 'level_2_terminating'
            //  but added after 'level_2_terminating'.
            // Because 'level_2_terminating' is a terminating role its own delegations are evaluated but no other
            // delegations are evaluated after it.
            // See § 5.6.7.2.1 and 5.6.7.2.2.
            'delegation is after terminating delegation' => [
                'NestedDelegatedErrors',
                'level_1_2_terminating_plus_1_more_unfindable.txt',
                [
                    'root' => 6,
                    'timestamp' => 6,
                    'snapshot' => 6,
                    'targets' => 6,
                    'unclaimed' => 3,
                    'level_2' => 2,
                    'level_2_after_terminating' => null,
                    'level_2_terminating' => null,
                    'level_3' => null,
                    'level_3_below_terminated' => null,
                ],
            ],
            // Test using the TerminatingDelegation fixture set.
            'TerminatingDelegation e.txt' => [
                'TerminatingDelegation',
                'e.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            'TerminatingDelegation f.txt' => [
                'TerminatingDelegation',
                'f.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => null,
                    'f' => null,
                ],
            ],
            // Test cases using the 'TopLevelTerminating' fixture set.
            'TopLevelTerminating b.txt' => [
                'TopLevelTerminating',
                'b.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => null,
                ],
            ],
            // Test cases using the 'NestedTerminatingNonDelegatingDelegation' fixture set.
            'NestedTerminatingNonDelegatingDelegation c.txt' => [
                'NestedTerminatingNonDelegatingDelegation',
                'c.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                ],
            ],
            'NestedTerminatingNonDelegatingDelegation d.txt' => [
                'NestedTerminatingNonDelegatingDelegation',
                'd.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => null,
                    'd' => null,
                ],
            ],
            // Test cases using the 'ThreeLevelDelegation' fixture set.
            // A search for non existent target should that matches the paths
            // should search the complete tree.
            'ThreeLevelDelegation z.txt' => [
                'ThreeLevelDelegation',
                'z.txt',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => 1,
                    'b' => 1,
                    'c' => 1,
                    'd' => 1,
                    'e' => 1,
                    'f' => 1,
                ],
            ],
            // A search for non existent target that does match the paths
            // should not search any of the tree.
            'ThreeLevelDelegation z.zip' => [
                'ThreeLevelDelegation',
                'z.zip',
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'targets' => 2,
                    'a' => null,
                    'b' => null,
                    'c' => null,
                    'd' => null,
                    'e' => null,
                    'f' => null,
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function providerKeyRotation(): array
    {
        return [
            'not rotated' => [
                'PublishedTwice',
                [
                    'root' => 2,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            // We expect the timestamp and snapshot metadata to be deleted from the client if either the
            // timestamp or snapshot roles' keys have been rotated.
            'timestamp rotated' => [
                'PublishedTwiceWithRotatedKeys_timestamp',
                [
                    'root' => 2,
                    'timestamp' => null,
                    'snapshot' => null,
                    'targets' => 1,
                ],
            ],
            'snapshot rotated' => [
                'PublishedTwiceWithRotatedKeys_snapshot',
                [
                    'root' => 2,
                    'timestamp' => null,
                    'snapshot' => null,
                    'targets' => 1,
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function providerFileNotFoundExceptions(): array
    {
        return static::getKeyedArray([
            // § 5.3.11
            [
                'Delegated',
                'timestamp.json',
                [
                    'root' => 4,
                    'timestamp' => null,
                    'snapshot' => null,
                    'targets' => 4,
                ],
            ],
            // § 5.3.11
            [
                'Delegated',
                '4.snapshot.json',
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => null,
                    'targets' => 4,
                ],
            ],
            [
                'Delegated',
                '4.targets.json',
                [
                    'root' => 4,
                    'timestamp' => 4,
                    'snapshot' => 4,
                    'targets' => 2,
                ],
            ],
            [
                'Simple',
                // Deleting timestamp.json and 1.snapshot.json from the server will cause Updater::updateTimestamp()
                // and Updater::refresh() to error out. That's fine in these cases, because we're not trying to finish
                // the refresh. This will implicitly check that Updater::updateRoot() doesn't erroneously think that
                // keys have been rotated, and therefore delete the local timestamp.json and snapshot.json.
                // @see ::testKeyRotation()
                'timestamp.json',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            [
                'Simple',
                '1.snapshot.json',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
            [
                'Simple',
                '1.targets.json',
                [
                    'root' => 1,
                    'timestamp' => 1,
                    'snapshot' => 1,
                    'targets' => 1,
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function providerUnsupportedRepo(): array
    {
        return [
            [
                [
                    'root' => 2,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'unsupported_target' => null,
                    // We cannot assert the starting versions of 'targets' because it has
                    // an unsupported field and would throw an exception when validating.
                ],
            ],
        ];
    }
}

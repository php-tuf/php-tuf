<?php

namespace Tuf\Tests\Client;

use Tuf\Exception\Attack\SignatureThresholdException;
use Tuf\Exception\MetadataException;

/**
 * Runs UpdaterTest's test cases on the fixtures with consistent snapshots.
 *
 * @testdox Updater with consistent snapshots
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
        $data = parent::providerRefreshRepository();
        $data['Delegated'][1]['root'] = 4;
        $data['NestedDelegated'][1]['root'] = 5;
        return $data;
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
        $data = parent::providerVerifiedDelegatedDownload();
        $data['level_1_target.txt'][2]['root'] = 5;
        $data['level_1_2_target.txt'][2]['root'] = 5;
        $data['level_1_2_terminating_findable.txt'][2]['root'] = 5;
        $data['level_1_2_3_below_non_terminating_target.txt'][2]['root'] = 5;
        $data['level_1_2_terminating_3_target.txt'][2]['root'] = 5;
        $data['level_1_2a_terminating_plus_1_more_findable.txt'][2]['root'] = 5;
        $data['TerminatingDelegation targets.txt'][2]['root'] = 2;
        $data['TerminatingDelegation a.txt'][2]['root'] = 2;
        $data['TerminatingDelegation b.txt'][2]['root'] = 2;
        $data['TerminatingDelegation c.txt'][2]['root'] = 2;
        $data['TerminatingDelegation d.txt'][2]['root'] = 2;
        $data['TopLevelTerminating a.txt'][2]['root'] = 2;
        $data['NestedTerminatingNonDelegatingDelegation a.txt'][2]['root'] = 2;
        $data['NestedTerminatingNonDelegatingDelegation b.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation targets.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation a.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation b.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation c.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation d.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation e.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation f.txt'][2]['root'] = 2;
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerDelegationErrors(): array
    {
        $data = parent::providerDelegationErrors();
        $data['no path match'][2]['root'] = 6;
        $data['matches parent delegation'][2]['root'] = 6;
        $data['delegated path does not match parent'][2]['root'] = 6;
        $data['delegated path does not match role'][2]['root'] = 6;
        $data['delegation is after terminating delegation'][2]['root'] = 6;
        $data['TerminatingDelegation e.txt'][2]['root'] = 2;
        $data['TerminatingDelegation f.txt'][2]['root'] = 2;
        $data['TopLevelTerminating b.txt'][2]['root'] = 2;
        $data['NestedTerminatingNonDelegatingDelegation c.txt'][2]['root'] = 2;
        $data['NestedTerminatingNonDelegatingDelegation d.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation z.txt'][2]['root'] = 2;
        $data['ThreeLevelDelegation z.zip'][2]['root'] = 2;
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerKeyRotation(): array
    {
        $data = parent::providerKeyRotation();
        $data['no keys rotated'][1]['root'] = 2;
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerFileNotFoundExceptions(): array
    {
        $data = parent::providerFileNotFoundExceptions();
        $data['timestamp.json in Delegated'][2]['root'] = 4;
        $data['snapshot.json in Delegated'][2]['root'] = 4;
        $data['snapshot.json in Delegated'][1] = '4.snapshot.json';
        $data['targets.json in Delegated'][2]['root'] = 4;
        $data['targets.json in Delegated'][1] = '4.targets.json';
        $data['snapshot.json in Simple'][1] = '1.snapshot.json';
        $data['targets.json in Simple'][1] = '1.targets.json';
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerUnsupportedRepo(): array
    {
        $data = parent::providerUnsupportedRepo();
        $data[0][0]['root'] = 2;
        return $data;
    }
}

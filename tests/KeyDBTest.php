<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\KeyDB;

/**
 * Tests KeyDB functionality.
 */
class KeyDBTest extends TestCase
{
    /**
     * Tests that computed key IDs match expected values in the repository.
     *
     * @dataProvider computeKeyIdProvider
     *
     * @param mixed[] $metadata
     *     The metadata for which to compute keys.
     * @param string[] $expected
     *     The expected key values.
     *
     * @return void
     */
    public function testComputeKeyId(array $metadata, array $expected) : void
    {
        $actual = KeyDB::computeKeyIds(new \ArrayObject($metadata));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for testComputeKeyId().
     *
     * @return array[][]
     *     An associative array of test cases, each containing:
     *     - metadata: The key metadata to check.
     *     - expected: The expected keys from the repository.
     */
    public function computeKeyIdProvider() : array
    {
        return [
            'case 1' => [
                'metadata' => [
                    'keyid_hash_algorithms' => ['sha256', 'sha512'],
                    'keytype' => 'ed25519',
                    'keyval' => ['public' => 'edcd0a32a07dce33f7c7873aaffbff36d20ea30787574ead335eefd337e4dacd'],
                    'scheme' => 'ed25519',
                ],
                'expected' => [
                    '59a4df8af818e9ed7abe0764c0b47b4240952aa0d179b5b78346c470ac30278d',
                    '594e8b4bdafc33fd87e9d03a95be13a6dc93a836086614fd421116d829af68d8f0110ae93e3dde9a246897fd85171455ea53191bb96cf9e589ba047d057dbd66',
                ],
            ],
        ];
    }
}

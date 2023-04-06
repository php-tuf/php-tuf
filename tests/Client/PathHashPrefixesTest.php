<?php

namespace Tuf\Tests\Client;

use Tuf\Exception\MetadataException;
use Tuf\Tests\ClientTestBase;

/**
 * Tests that path_hash_prefixes are not supported in delegated roles.
 */
class PathHashPrefixesTest extends ClientTestBase
{
    public function provider(): array
    {
        return [
            'consistent' => [
                'consistent',
                [
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'unsupported_target' => null,
                ],
            ],
            'inconsistent' => [
                'inconsistent',
                [
                    'root' => 1,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'unsupported_target' => null,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function test(string $fixtureVariant, array $expectedUpdatedVersion): void
    {
        // @todo Actually test looking up a target by path hash prefixing.
        $this->assertTrue(TRUE);
    }
}

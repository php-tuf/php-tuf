<?php

namespace Tuf\Tests\Unit;

use Tuf\DelegatedRole;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Tuf\DelegatedRole
 */
class DelegatedRoleTest extends TestCase
{

    /**
     * @param string $target
     * @param array $paths
     * @param bool $expected
     *
     * @dataProvider providerMatchesRolePath
     */
    public function testMatchesRolePath(string $target, array $paths, bool $expected): void
    {
        $data = new \ArrayObject([
            'name' => 'some_role',
            'threshold' => 1,
            'keyids' => ['key1', 'key2'],
            'paths' => $paths,
        ]);
        $role = DelegatedRole::createFromMetadata($data);
        self::assertSame($expected, $role->matchesRolePath($target));
    }

    public function providerMatchesRolePath()
    {
        return [
            'match' => [
                '/dirA/match.txt',
                [
                    '/dirA/*.txt',
                    '/dirB/*.txt',
                ],
                true,
            ],
            'no match' => [
                '/dirA/nomatch.zip',
                [
                    '/dirA/*.txt',
                    '/dirB/*.txt',
                ],
                false,
            ],
        ];
    }
}

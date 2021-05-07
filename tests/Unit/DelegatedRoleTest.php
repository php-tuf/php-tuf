<?php

namespace Tuf\Tests\Unit;

use Tuf\DelegatedRole;
use Tuf\Role;

/**
 * @coversDefaultClass \Tuf\DelegatedRole
 */
class DelegatedRoleTest extends RoleTest
{

    /**
     * The test role.
     *
     * @var \Tuf\DelegatedRole
     */
    protected $role;

    /**
     * {@inheritdoc}
     */
    public function testCreateFromMetadata(): void
    {
        parent::testCreateFromMetadata();
        self::assertFalse($this->role->isTerminating());
    }

    /**
     * {@inheritdoc}
     */
    public function providerInvalidMetadata(): array
    {
        return [
            'nothing' => [[]],
            'no paths' => [
                [
                    'name' => 'a role',
                    'threshold' => 1,
                    'keyids' => ['good_key'],
                    'terminating' => false,
                ],
            ],
        ];
    }

    /**
     * @covers ::matchesPath
     *
     * @param string $target
     *   The test target path.
     * @param array $paths
     *   The paths to set.
     * @param bool $expected
     *   The expected result.
     *
     * @dataProvider providerMatchesRolePath
     */
    public function testMatchesRolePath(string $target, array $paths, bool $expected): void
    {
        $data = [
            'name' => 'some_role',
            'threshold' => 1,
            'keyids' => ['key1', 'key2'],
            'terminating' => false,
            'paths' => $paths,
        ];
        self::assertSame($expected, $this->createTestRole($data)->matchesPath($target));
    }

    /**
     * Data provider for testMatchesRolePath().
     *
     * @return array[]
     */
    public function providerMatchesRolePath(): array
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

    /**
     * {@inheritdoc}
     */
    protected function createTestRole(?array $data = null): Role
    {
        $data = $data ?? [
            'name' => 'my_role',
            'threshold' => 1000,
            'keyids' => [
                'good_key_1',
                'good_key_2',
            ],
            'terminating' => false,
            'paths' => [
                'path1',
                'path2',
            ],
        ];
        return DelegatedRole::createFromMetadata(new \ArrayObject($data));
    }
}

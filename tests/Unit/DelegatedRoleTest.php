<?php

namespace Tuf\Tests\Unit;

use Tuf\DelegatedRole;
use Tuf\Exception\MetadataException;
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
        return DelegatedRole::createFromMetadata($data);
    }

    public function testNoPathsOrPrefixes(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Either paths or path_hash_prefixes must be specified, but not both.');

        $this->createTestRole([
            'name' => 'my_role',
            'threshold' => 1000,
            'keyids' => [
                'good_key_1',
                'good_key_2',
            ],
            'terminating' => false,
        ]);
    }

    public function testPathsAndPrefixes(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Either paths or path_hash_prefixes must be specified, but not both.');

        $this->createTestRole([
            'name' => 'my_role',
            'threshold' => 1000,
            'keyids' => [
                'good_key_1',
                'good_key_2',
            ],
            'terminating' => false,
            'paths' => [],
            'path_hash_prefixes' => [],
        ]);
    }

    /**
     * @testWith ["paths", "Not an array!", ["paths"], "This value should be of type array."]
     *   ["paths", [""], ["paths", 0], "This value should not be blank."]
     *   ["paths", [38], ["paths", 0], "This value should be of type string."]
     *   ["path_hash_prefixes", "Not an array!", ["path_hash_prefixes"], "This value should be of type array."]
     *   ["path_hash_prefixes", [""], ["path_hash_prefixes", 0], "This value should not be blank."]
     *   ["path_hash_prefixes", [38], ["path_hash_prefixes", 0], "This value should be of type string."]
     */
    public function testPathsAndPrefixesMustBeArrays(string $key, mixed $value, array $propertyPath, string $expectedError): void
    {
        $propertyPath = preg_quote('[' . implode('][', $propertyPath) . ']');
        $expectedError = preg_quote($expectedError);

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches("/Array$propertyPath:\s*$expectedError/");

        $this->createTestRole([
            'name' => 'my_role',
            'threshold' => 1000,
            'keyids' => [
                'good_key_1',
                'good_key_2',
            ],
            'terminating' => false,
            $key => $value,
        ]);
    }
}

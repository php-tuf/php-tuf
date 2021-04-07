<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tuf\Exception\MetadataException;
use Tuf\Role;

/**
 * @coversDefaultClass \Tuf\Role
 */
class RoleTest extends TestCase
{

    /**
     * @covers ::createFromMetadata
     * @covers ::getName
     * @covers ::getThreshold
     */
    public function testCreateFromMetadata(): void
    {
        $role = Role::createFromMetadata(
            new \ArrayObject([
                'threshold' => 1000,
                'keyids' => [
                    'good_key_1',
                    'good_key_2',
                ]
            ]),
            'my_role'
        );
        self::assertSame(1000, $role->getThreshold());
        self::assertSame('my_role', $role->getName());
    }

    /**
     * @covers ::createFromMetadata
     *
     * @param $data
     *   Invalid data.
     *
     * @dataProvider providerInvalidMetadata
     */
    public function testInvalidMetadata($data): void
    {
        $this->expectException(MetadataException::class);
        Role::createFromMetadata(
            new \ArrayObject($data),
            'my_role'
        );
    }

    /**
     * Data provider for testInvalidMetadata().
     *
     * @return array[]
     */
    public function providerInvalidMetadata(): array
    {
        return [
            'nothing' => [[]],
            'no keyids' => [['threshold' => 1]],
            'no threshold' => [['keyids' => ['good_key']]],
            'invalid threshold' => [['threshold' => '1', 'keyids' => ['good_key']]],
            'invalid keyids' => [['threshold' => 1, 'keyids' => 'good_key_1,good_key_2']],
        ];
    }

    /**
     * @covers ::isKeyIdAcceptable
     *
     * @param string $keyId
     * @param bool $expected
     *
     * @dataProvider providerIsKeyIdAcceptable
     */
    public function testIsKeyIdAcceptable(string $keyId, bool $expected): void
    {
        $role = Role::createFromMetadata(
            new \ArrayObject([
                'threshold' => 1000,
                'keyids' => [
                    'good_key_1',
                    'good_key_2',
                ]
            ]),
            'myrole'
        );
        self::assertSame($expected, $role->isKeyIdAcceptable($keyId));
    }

    /**
     * Data provider for testIsKeyIdAcceptable().
     *
     * @return array[]
     */
    public function providerIsKeyIdAcceptable(): array
    {
        return [
            ['good_key_1', true],
            ['good_key_2', true],
            ['bad_key', false],
        ];
    }
}

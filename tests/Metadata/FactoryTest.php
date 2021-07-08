<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\Factory;
use PHPUnit\Framework\TestCase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\FixturesTrait;
use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * @coversDefaultClass \Tuf\Metadata\Factory
 */
class FactoryTest extends TestCase
{
    use FixturesTrait;
    use UtilsTrait;

    /**
     * @covers ::load
     *
     * @dataProvider providerLoad
     *
     * @param string $class
     *   The class name of the expected metadata.
     * @param string $role
     *   The role to load.
     */
    public function testLoad(string $class, string $role): void
    {
        $clientStorage = static::loadFixtureIntoMemory('TUFTestFixtureDelegated');
        $factory = new Factory($clientStorage);

        $metadata = $factory->load($role);
        self::assertInstanceOf($class, $metadata);
        self::assertSame($class::TYPE, $metadata->getType());
        self::assertSame($role, $metadata->getRole());
    }

    /**
     * Data provider for testLoad().
     *
     * @return array
     */
    public function providerLoad(): array
    {
        return self::getKeyedArray([
            [RootMetadata::class, 'root'],
            [TimestampMetadata::class, 'timestamp'],
            [SnapshotMetadata::class, 'snapshot'],
            [TargetsMetadata::class, 'targets'],
            [TargetsMetadata::class, 'unclaimed'],
        ]);
    }
}

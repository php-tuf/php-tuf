<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\Factory;
use PHPUnit\Framework\TestCase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

/**
 * @coversDefaultClass \Tuf\Metadata\Factory
 */
class FactoryTest extends TestCase
{
    use MemoryStorageLoaderTrait;

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
        $localRepo = $this->memoryStorageFromFixture('TUFTestFixtureDelegated', 'client/metadata/current');
        $factory = new Factory($localRepo);

        $metadata = $factory->load($role);
        self::assertInstanceOf($class, $metadata);
        self::assertEquals($class::TYPE, $metadata->getType());
        self::assertEquals($role, $metadata->getRole());
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

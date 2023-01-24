<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Client\DurableStorage\FileStorage;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;

/**
 * @coversDefaultClass \Tuf\Client\DurableStorage\FileStorage
 */
class FileStorageTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Tests creating a FileStorage object with an invalid directory.
     *
     * @return void
     *
     * @covers ::pathWithBasePath
     */
    public function testCreateWithInvalidDirectory(): void
    {
        $dir = '/nonsensedirdoesnotexist' . uniqid();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage("Cannot initialize filesystem local state: '$dir' is not a directory.");
        new FileStorage($dir);
    }

    /**
     * @covers ::getRoot
     */
    public function testExceptionIfRootMetadataDoesNotExist(): void
    {
        $storage = new FileStorage(sys_get_temp_dir());

        $this->expectException('LogicException');
        $this->expectExceptionMessage('Could not load root metadata.');
        $storage->getRoot();
    }

    public function providerMetadataStorage(): array
    {
        return [
            'root' => [
                RootMetadata::class,
                'root',
                'setRoot',
                'root.json',
            ],
            'timestamp' => [
                TimestampMetadata::class,
                'timestamp',
                'setTimestamp',
                'timestamp.json',
            ],
            'snapshot' => [
                SnapshotMetadata::class,
                'snapshot',
                'setSnapshot',
                'snapshot.json',
            ],
            'targets' => [
                TargetsMetadata::class,
                'targets',
                'setTargets',
                'targets.json',
            ],
            'delegated role' => [
                TargetsMetadata::class,
                'delegated',
                'setTargets',
                'delegated.json',
            ],
        ];
    }

    /**
     * Tests storing metadata with a FileStorage object.
     *
     * @covers ::setRoot
     * @covers ::setTimestamp
     * @covers ::setSnapshot
     * @covers ::setTargets
     * @covers ::delete
     *
     * @dataProvider providerMetadataStorage
     *
     * @testdox Storing $_dataName metadata
     */
    public function testMetadataStorage(string $metadataClass, string $role, string $methodToCall, string $expectedFileName): void
    {
        $dir = sys_get_temp_dir();
        $storage = new FileStorage($dir);

        $metadata = $this->prophesize($metadataClass);
        $metadata->getRole()->willReturn($role);
        $metadata->getSource()->willReturn("From hell's heart, I refactor thee!");
        $metadata->ensureIsTrusted()->shouldBeCalled();
        $storage->$methodToCall($metadata->reveal());

        $filePath = $dir . '/' . $expectedFileName;
        $this->assertFileExists($filePath);
        $this->assertSame("From hell's heart, I refactor thee!", file_get_contents($filePath));

        $storage->delete($role);
        $this->assertFileDoesNotExist($filePath);
    }
}

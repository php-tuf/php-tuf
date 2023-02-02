<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Repository;
use Tuf\Exception\DownloadSizeException;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\FileLoader;
use Tuf\Tests\TestHelpers\FixturesTrait;
use Tuf\Tests\TestHelpers\MaxBytesSpyLoader;

/**
 * @covers \Tuf\Client\Repository
 */
class RepositoryTest extends TestCase
{
    use FixturesTrait;

    public function testRepository(): void
    {
        $baseDir = static::getFixturePath('Delegated', 'consistent/server/metadata');
        $loader = new MaxBytesSpyLoader(new FileLoader($baseDir));
        $repository = new Repository($loader);

        $this->assertInstanceOf(RootMetadata::class, $repository->getRoot(1));
        $this->assertSame(Repository::MAX_BYTES, $loader->maxBytes['1.root.json'][0]);

        $this->assertNull($repository->getRoot(999));
        $this->assertSame(Repository::MAX_BYTES, $loader->maxBytes['999.root.json'][0]);

        $this->assertInstanceOf(TimestampMetadata::class, $repository->getTimestamp());
        $this->assertSame(Repository::MAX_BYTES, $loader->maxBytes['timestamp.json'][0]);

        foreach ([1, null] as $version) {
            $fileName = isset($version) ? "$version.snapshot.json" : 'snapshot.json';

            $this->assertInstanceOf(SnapshotMetadata::class, $repository->getSnapshot($version));
            $this->assertSame(Repository::MAX_BYTES, $loader->maxBytes[$fileName][0]);

            $fileSize = filesize($baseDir . '/' . $fileName);
            $this->assertInstanceOf(SnapshotMetadata::class, $repository->getSnapshot($version, $fileSize));
            $this->assertSame($fileSize, $loader->maxBytes[$fileName][1]);

            // Ensure that the file size is always checked, even if Repository
            // was not given a SizeCheckingLoader.
            try {
                $repository->getSnapshot($version, 1);
                $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
            } catch (DownloadSizeException $e) {
                $this->assertSame("$fileName exceeded 1 bytes", $e->getMessage());
            }

            foreach (['targets', 'unclaimed'] as $role) {
                $fileName = isset($version) ? "$version.$role.json" : "$role.json";

                $this->assertInstanceOf(TargetsMetadata::class, $repository->getTargets($version, $role));
                $this->assertSame(Repository::MAX_BYTES, $loader->maxBytes[$fileName][0]);

                $fileSize = filesize($baseDir . '/' . $fileName);
                $this->assertInstanceOf(TargetsMetadata::class, $repository->getTargets($version, $role, $fileSize));
                $this->assertSame($fileSize, $loader->maxBytes[$fileName][1]);

                try {
                    $repository->getTargets($version, $role, 1);
                    $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
                } catch (DownloadSizeException $e) {
                    $this->assertSame("$fileName exceeded 1 bytes",
                        $e->getMessage());
                }
            }
        }
    }
}

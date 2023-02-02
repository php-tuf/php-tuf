<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Tuf\Downloader\SizeCheckingDownloader;
use Tuf\Exception\MetadataException;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Repository;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\TestDownloader;

/**
 * @covers \Tuf\Repository
 */
class RepositoryTest extends TestCase
{
    public function providerFetchMetadata(): array
    {
        return [
            'root' => [
                '1.root.json',
                'getRoot',
                [1],
                RootMetadata::class,
            ],
            'timestamp' => [
                'timestamp.json',
                'getTimestamp',
                [],
                TimestampMetadata::class,
            ],
            'snapshot, without version' => [
                'snapshot.json',
                'getSnapshot',
                [null],
                SnapshotMetadata::class,
            ],
            'snapshot, with version' => [
                '1.snapshot.json',
                'getSnapshot',
                [1],
                SnapshotMetadata::class,
            ],
            'targets, without version' => [
                'targets.json',
                'getTargets',
                [null],
                TargetsMetadata::class,
            ],
            'targets, with version' => [
                '1.targets.json',
                'getTargets',
                [1],
                TargetsMetadata::class,
            ],
            'delegated role, without version' => [
                'unclaimed.json',
                'getTargets',
                [null, 'unclaimed'],
                TargetsMetadata::class,
            ],
            'delegated role, with version' => [
                '1.unclaimed.json',
                'getTargets',
                [1, 'unclaimed'],
                TargetsMetadata::class,
            ],
        ];
    }

    /**
     * @dataProvider providerFetchMetadata
     */
    public function testFetchMetadata(string $fileName, string $method, array $arguments, string $metadataClass): void
    {
        $downloader = new TestDownloader();
        $repository = new Repository(new SizeCheckingDownloader($downloader));

        $file = fopen(__DIR__ . "/../../fixtures/Delegated/consistent/server/metadata/$fileName", 'r');
        $this->assertIsResource($file);
        $downloader->set($fileName, Utils::streamFor($file));

        $metadata = $repository->$method(...$arguments)->wait();
        $this->assertInstanceOf($metadataClass, $metadata);

        // If we were fetching targets metadata, ensure the metadata object we
        // got back has the correct role.
        if ($metadata instanceof TargetsMetadata) {
            $this->assertSame($arguments[1] ?? 'targets', $metadata->getRole());
        }

        // If the response is a 404, we should get an exception for everything
        // except the root metadata, which should merely return null.
        $downloader->set($fileName, 404);
        try {
            $metadata = $repository->$method(...$arguments)->wait();
            // If we didn't get an exception, ensure we were trying to load
            // non-existent root metadata, and that we got null back.
            $this->assertSame(RootMetadata::class, $metadataClass);
            $this->assertNull($metadata);
        } catch (RepoFileNotFound $e) {
            $this->assertNotSame(RootMetadata::class, $metadataClass);
            $this->assertSame("$fileName not found.", $e->getMessage());
        }

        $downloader->set($fileName, '{"invalid": "data"}');
        // If createFromJson() cannot validate the JSON returned by the server,
        // we should get a MetadataException right away.
        $this->expectException(MetadataException::class);
        $repository->$method(...$arguments)->wait();
    }
}

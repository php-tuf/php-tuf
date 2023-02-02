<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Tuf\Client\Repository;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\SizeCheckingLoader;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\Client\TestLoader;
use Tuf\Tests\TestHelpers\FixturesTrait;

/**
 * @covers \Tuf\Client\Repository
 */
class RepositoryTest extends TestCase implements LoaderInterface
{
    use FixturesTrait;

    private array $maxBytes = [];

    private TestLoader $loader;

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): StreamInterface
    {
        $this->maxBytes[$uri][] = $maxBytes;
        return $this->loader->load($uri, $maxBytes);
    }

    public function testRepository(): void
    {
        $baseDir = static::getFixturePath('Delegated', 'consistent');
        $this->loader = new TestLoader($baseDir);
        $repository = new Repository(new SizeCheckingLoader($this));

        $this->assertInstanceOf(RootMetadata::class, $repository->getRoot(1));
        $this->assertSame(Repository::MAX_BYTES, $this->maxBytes['1.root.json'][0]);

        $this->assertNull($repository->getRoot(999));
        $this->assertSame(Repository::MAX_BYTES, $this->maxBytes['999.root.json'][0]);

        $this->assertInstanceOf(TimestampMetadata::class, $repository->getTimestamp());
        $this->assertSame(Repository::MAX_BYTES, $this->maxBytes['timestamp.json'][0]);

        foreach ([1, null] as $version) {
            $fileName = isset($version) ? "$version.snapshot.json" : 'snapshot.json';

            $this->assertInstanceOf(SnapshotMetadata::class, $repository->getSnapshot($version));
            $this->assertSame(Repository::MAX_BYTES, $this->maxBytes[$fileName][0]);

            $metadataDir = $baseDir . '/server/metadata';
            $fileSize = filesize($metadataDir . '/' . $fileName);
            $this->assertInstanceOf(SnapshotMetadata::class, $repository->getSnapshot($version, $fileSize));
            $this->assertSame($fileSize, $this->maxBytes[$fileName][1]);

            foreach (['targets', 'unclaimed'] as $role) {
                $fileName = isset($version) ? "$version.$role.json" : "$role.json";

                $this->assertInstanceOf(TargetsMetadata::class, $repository->getTargets($version, $role));
                $this->assertSame(Repository::MAX_BYTES, $this->maxBytes[$fileName][0]);

                $fileSize = filesize($metadataDir . '/' . $fileName);
                $this->assertInstanceOf(TargetsMetadata::class, $repository->getTargets($version, $role, $fileSize));
                $this->assertSame($fileSize, $this->maxBytes[$fileName][1]);
            }
        }
    }
}

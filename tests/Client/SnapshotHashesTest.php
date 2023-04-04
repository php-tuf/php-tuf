<?php

namespace Tuf\Tests\Client;

use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Exception\MetadataException;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Tests\ClientTestBase;

/**
 * Tests that hashes in the snapshot metadata are verified.
 */
class SnapshotHashesTest extends ClientTestBase
{
    use ProphecyTrait;

    /**
     * @testWith ["consistent"]
     *   ["inconsistent"]
     */
    public function testSnapshotHashes(string $fixtureVariant): void
    {
        $this->loadClientAndServerFilesFromFixture("Simple/$fixtureVariant");

        $targetsMetadata = $this->prophesize(TargetsMetadata::class);
        $targetsMetadata->getRole()->willReturn('targets');
        $targetsMetadata->getSource()->willReturn('invalid data');
        $this->serverMetadata->targets['targets'][1] = $targetsMetadata->reveal();

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage("The 'targets' contents does not match hash 'sha256' specified in the 'snapshot' metadata.");
        $this->getUpdater()->refresh();
    }
}

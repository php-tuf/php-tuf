<?php

namespace Tuf\Metadata;

use Tuf\Exception\MetadataException;

/**
 * A trait that implements MetaFileInterface.
 */
trait MetaFileInfoTrait
{

    /**
     * {@inheritdoc}
     */
    public function getFileMetaInfo(string $key):?\ArrayObject
    {
        $signed = $this->getSigned();
        return $signed['meta'][$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyNewMetaData(MetadataBase $newMetadata):void
    {
        $fileInfo = $this->getFileMetaInfo($newMetadata->getType() . '.json');
        $expectedVersion = $fileInfo['version'];
        if ($expectedVersion !== $newMetadata->getVersion()) {
            throw new MetadataException("Expected {$newMetadata->getType()} version {$expectedVersion} does not match actual version {$newMetadata->getVersion()}.");
        }
        if (isset($fileInfo['hashes'])) {
            foreach ($fileInfo['hashes'] as $algo => $hash) {
                if ($hash !== hash($algo, $newMetadata->getSource())) {
                    /** @var \Tuf\Metadata\MetadataBase $authorityMetadata */
                    throw new MetadataException("The '{$newMetadata->getType()}' contents does not match hash '$algo' specified in the '{$this->getType()}' metadata.");
                }
            }
        }
    }
}

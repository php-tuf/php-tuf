<?php


namespace Tuf\Verifier;

use Tuf\Exception\MetadataException;

/**
 * Interface for verifiers where the metadata is referenced in another metadata file.
 */
trait ReferencedMetadataVerifierTrait
{

    /**
     * @var \Tuf\Metadata\FileInfoMetadataBase
     */
    protected $referencingMetadata;



    /**
     * Verifies the hashes of a new metadata object from information in the current object.
     *
     * @param \Tuf\Metadata\MetadataBase $newMetadata
     *   The new metadata object.
     *
     * @throws \Tuf\Exception\MetadataException
     *   Thrown if the new metadata object cannot be verified.
     *
     * @return void
     */
    public function verifyNewHashes(): void
    {
        $role = $this->untrustedMetadata->getRole();
        $fileInfo = $this->referencingMetadata->getFileMetaInfo($role . '.json');
        if (isset($fileInfo['hashes'])) {
            foreach ($fileInfo['hashes'] as $algo => $hash) {
                if ($hash !== hash($algo, $this->untrustedMetadata->getSource())) {
                    /** @var \Tuf\Metadata\MetadataBase $authorityMetadata */
                    throw new MetadataException("The '{$role}' contents does not match hash '$algo' specified in the '{$this->untrustedMetadata->getType()}' metadata.");
                }
            }
        }
    }

    /**
     * Verifies a the version of a new metadata object from information in the current object.
     *
     * @param \Tuf\Metadata\MetadataBase $newMetadata
     *   The new metadata object.
     *
     * @throws \Tuf\Exception\MetadataException
     *   Thrown if the new metadata object cannot be verified.
     *
     * @return void
     */
    public function verifyNewVersion(): void
    {
        $role = $this->untrustedMetadata->getRole();
        $fileInfo = $this->referencingMetadata->getFileMetaInfo($role . '.json');
        $expectedVersion = $fileInfo['version'];
        if ($expectedVersion !== $this->untrustedMetadata->getVersion()) {
            throw new MetadataException("Expected {$role} version {$expectedVersion} does not match actual version {$this->untrustedMetadata->getVersion()}.");
        }
    }
}

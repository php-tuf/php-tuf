<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Exception\MetadataException;
use Tuf\Metadata\FileInfoMetadataBase;
use Tuf\Metadata\MetadataBase;

/**
 * Interface for verifiers where the metadata is referenced by another metadata file.
 */
trait ReferencedMetadataVerifierTrait
{

    /**
     * @var \Tuf\Metadata\FileInfoMetadataBase
     */
    protected $referrer;

    protected function setReferrer(FileInfoMetadataBase $referrer): void
    {
        $referrer->ensureIsTrusted();
        $this->referrer = $referrer;
    }


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
    public function verifyNewHashes(MetadataBase $untrustedMetadata): void
    {
        $role = $untrustedMetadata->getRole();
        $fileInfo = $this->referrer->getFileMetaInfo($role . '.json');
        if (isset($fileInfo['hashes'])) {
            foreach ($fileInfo['hashes'] as $algo => $hash) {
                if ($hash !== hash($algo, $untrustedMetadata->getSource())) {
                    /** @var \Tuf\Metadata\MetadataBase $authorityMetadata */
                    throw new MetadataException("The '{$role}' contents does not match hash '$algo' specified in the '{$this->referrer->getType()}' metadata.");
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
    public function verifyNewVersion($untrustedMetadata): void
    {
        $role = $untrustedMetadata->getRole();
        $fileInfo = $this->referrer->getFileMetaInfo($role . '.json');
        $expectedVersion = $fileInfo['version'];
        if ($expectedVersion !== $untrustedMetadata->getVersion()) {
            throw new MetadataException("Expected {$role} version {$expectedVersion} does not match actual version {$untrustedMetadata->getVersion()}.");
        }
    }
}

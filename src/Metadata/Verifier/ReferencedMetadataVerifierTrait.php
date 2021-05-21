<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Exception\MetadataException;
use Tuf\Metadata\FileInfoMetadataBase;
use Tuf\Metadata\MetadataBase;

/**
 * Helper methods for verifiers where the metadata is referenced by another metadata file.
 */
trait ReferencedMetadataVerifierTrait
{

    /**
     * The authority metadata which has the expected hashes and version number of the untrusted metadata.
     *
     * @var \Tuf\Metadata\FileInfoMetadataBase
     */
    protected $authorityMetadata;

    protected function setAuthorityMetadata(FileInfoMetadataBase $authority): void
    {
        $authority->ensureIsTrusted();
        $this->authorityMetadata = $authority;
    }


    /**
     * Verifies the hashes of a untrusted metadata from hashes in the authority metadata.
     *
     * @param \Tuf\Metadata\MetadataBase $untrustedMetadata
     *   The untrusted metadata.
     *
     * @throws \Tuf\Exception\MetadataException
     *   Thrown if the new metadata object cannot be verified.
     *
     * @return void
     */
    protected function verifyAgainstAuthorityHashes(MetadataBase $untrustedMetadata): void
    {
        $role = $untrustedMetadata->getRole();
        $fileInfo = $this->authorityMetadata->getFileMetaInfo($role . '.json');
        if (isset($fileInfo['hashes'])) {
            foreach ($fileInfo['hashes'] as $algo => $hash) {
                if ($hash !== hash($algo, $untrustedMetadata->getSource())) {
                    /** @var \Tuf\Metadata\MetadataBase $authorityMetadata */
                    throw new MetadataException("The '{$role}' contents does not match hash '$algo' specified in the '{$this->authorityMetadata->getType()}' metadata.");
                }
            }
        }
    }

    /**
     * Verifies the version of a untrusted metadata against the version in authority metadata.
     *
     * @param \Tuf\Metadata\MetadataBase $untrustedMetadata
     *   The untrusted metadata.
     *
     * @throws \Tuf\Exception\MetadataException
     *   Thrown if the new metadata object cannot be verified.
     *
     * @return void
     */
    protected function verifyAgainstAuthorityVersion(MetadataBase $untrustedMetadata): void
    {
        $role = $untrustedMetadata->getRole();
        $fileInfo = $this->authorityMetadata->getFileMetaInfo($role . '.json');
        $expectedVersion = $fileInfo['version'];
        if ($expectedVersion !== $untrustedMetadata->getVersion()) {
            throw new MetadataException("Expected {$role} version {$expectedVersion} does not match actual version {$untrustedMetadata->getVersion()}.");
        }
    }
}

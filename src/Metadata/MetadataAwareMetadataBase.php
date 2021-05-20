<?php

namespace Tuf\Metadata;

use Tuf\Exception\MetadataException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;

/**
 * Base class for metadata that contains information about other metadata.
 */
abstract class MetadataAwareMetadataBase extends MetadataBase
{
    /**
     * {@inheritDoc}
     */
    public function checkRollbackAttack(MetadataBase $remoteMetadata, int $expectedRemoteVersion = null): void
    {
        parent::checkRollbackAttack($remoteMetadata, $expectedRemoteVersion);

        if (!$remoteMetadata instanceof MetadataAwareMetadataBase) {
            throw new \InvalidArgumentException(__METHOD__ . '() can only be use with another instance of ' . MetadataAwareMetadataBase::class);
        }

        $type = $this->getType();
        $localMetaFileInfos = $this->getSigned()['meta'];
        foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
            /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $remoteMetadata */
            if ($remoteFileInfo = $remoteMetadata->getFileMetaInfo($fileName, true)) {
                if ($remoteFileInfo['version'] < $localFileInfo['version']) {
                    $message = "Remote $type metadata file '$fileName' version \"${$remoteFileInfo['version']}\" " .
                        "is less than previously seen  version \"${$localFileInfo['version']}\"";
                    throw new RollbackAttackException($message);
                }
            }
        }
    }

    /**
     * Gets file information value under the 'meta' key.
     *
     * @param string $key
     *   The array key under 'meta'.
     * @param boolean $allowUntrustedAccess
     *   Whether this method should access even if the metadata is not trusted.
     *
     * @return \ArrayObject|null
     *   The file information if available or null if not set.
     */
    public function getFileMetaInfo(string $key, bool $allowUntrustedAccess = false): ?\ArrayObject
    {
        $this->ensureIsTrusted($allowUntrustedAccess);
        $signed = $this->getSigned();
        return $signed['meta'][$key] ?? null;
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
    public function verifyNewHashes(MetadataBase $newMetadata): void
    {
        $this->ensureIsTrusted();
        $role = $newMetadata->getRole();
        $fileInfo = $this->getFileMetaInfo($role . '.json');
        if (isset($fileInfo['hashes'])) {
            foreach ($fileInfo['hashes'] as $algo => $hash) {
                if ($hash !== hash($algo, $newMetadata->getSource())) {
                    /** @var \Tuf\Metadata\MetadataBase $authorityMetadata */
                    throw new MetadataException("The '{$role}' contents does not match hash '$algo' specified in the '{$this->getType()}' metadata.");
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
    public function verifyNewVersion(MetadataBase $newMetadata): void
    {
        $this->ensureIsTrusted();
        $role = $newMetadata->getRole();
        $fileInfo = $this->getFileMetaInfo($role . '.json');
        $expectedVersion = $fileInfo['version'];
        if ($expectedVersion !== $newMetadata->getVersion()) {
            throw new MetadataException("Expected {$role} version {$expectedVersion} does not match actual version {$newMetadata->getVersion()}.");
        }
    }
}

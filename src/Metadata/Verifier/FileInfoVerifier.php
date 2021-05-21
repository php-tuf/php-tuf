<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;

/**
 * Verifier for metadata classes that have information about other files.
 */
abstract class FileInfoVerifier extends VerifierBase
{
    /**
     * The trusted metadata, if any.
     *
     * @var \Tuf\Metadata\FileInfoMetadataBase
     */
    protected $trustedMetadata;

    /**
     * {@inheritdoc}
     */
    protected function checkRollbackAttack(MetadataBase $untrustedMetadata): void
    {
        parent::checkRollbackAttack($untrustedMetadata);
        // Check that all files in the trusted/local metadata info under the 'meta' section are less or equal to
        // the same files in the new metadata info.
        // For 'snapshot' type this is TUF-SPEC-v1.0.16 Section 5.4.4
        // For 'timestamp' type this TUF-SPEC-v1.0.16 Section 5.3.2.2
        $localMetaFileInfos = $this->trustedMetadata->getSigned()['meta'];
        $type = $this->trustedMetadata->getType();
        foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
            /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $untrustedMetadata */
            if ($remoteFileInfo = $untrustedMetadata->getFileMetaInfo($fileName, true)) {
                if ($remoteFileInfo['version'] < $localFileInfo['version']) {
                    $message = "Remote $type metadata file '$fileName' version \"${$remoteFileInfo['version']}\" " .
                      "is less than previously seen  version \"${$localFileInfo['version']}\"";
                    throw new RollbackAttackException($message);
                }
            }
        }
    }
}

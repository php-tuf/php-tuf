<?php

namespace Tuf\Verifier;

use Tuf\Exception\PotentialAttackException\RollbackAttackException;

/**
 * Verifier for metadata classes that have information about other files.
 */
abstract class FileInfoMetadataVerifier extends MetaDataVerifierBase
{

    /**
     * @var \Tuf\Metadata\FileInfoMetadataBase
     */
    protected $trustedMetadata;
    /**
     * @var \Tuf\Metadata\FileInfoMetadataBase
     */
    protected $untrustedMetadata;


    protected function checkRollbackAttack(): void
    {
        parent::checkRollbackAttack();
        // Check that all files in the trusted/local metadata info under the 'meta' section are less or equal to
        // the same files in the new metadata info.
        // For 'snapshot' type this is TUF-SPEC-v1.0.16 Section 5.4.4
        // For 'timestamp' type this TUF-SPEC-v1.0.16 Section 5.3.2.2
        $localMetaFileInfos = $this->trustedMetadata->getSigned()['meta'];
        $type = $this->trustedMetadata->getType();
        foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
            /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $this->untrustedMetadata */
            if ($remoteFileInfo = $this->untrustedMetadata->getFileMetaInfo($fileName, true)) {
                if ($remoteFileInfo['version'] < $localFileInfo['version']) {
                    $message = "Remote $type metadata file '$fileName' version \"${$remoteFileInfo['version']}\" " .
                      "is less than previously seen  version \"${$localFileInfo['version']}\"";
                    throw new RollbackAttackException($message);
                }
            }
        }
    }
}

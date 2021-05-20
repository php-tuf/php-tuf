<?php


namespace Tuf\Verifier;

use Tuf\Exception\PotentialAttackException\RollbackAttackException;

class SnapshootMetadataVerifier extends FileInfoMetadataVerifier
{
    use ReferencedMetadataVerifierTrait;

    public function verify()
    {
        // TODO: Implement verify() method.
    }

    protected function checkRollbackAttack(): void
    {
        parent::checkRollbackAttack();
        $localMetaFileInfos = $this->trustedMetadata->getSigned()['meta'];
        $type = $this->trustedMetadata->getType();
        foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
            /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $this->untrustedMetadata */
            if (!$this->untrustedMetadata->getFileMetaInfo($fileName, true)) {
                // TUF-SPEC-v1.0.16 Section 5.4.4
                // Any targets metadata filename that was listed in the trusted snapshot metadata file, if any, MUST
                // continue to be listed in the new snapshot metadata file.
                throw new RollbackAttackException("Remote snapshot metadata file references '$fileName' but this is not present in the remote file");
            }
        }
    }
}

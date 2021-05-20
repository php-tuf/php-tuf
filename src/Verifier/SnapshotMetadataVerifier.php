<?php


namespace Tuf\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\TimestampMetadata;

class SnapshotMetadataVerifier extends FileInfoMetadataVerifier
{
    use ReferencedMetadataVerifierTrait;

    public function __construct(
      SignatureVerifier $signatureVerifier,
      \DateTimeImmutable $metadataExpiration,
      MetadataBase $untrustedMetadata,
      ?MetadataBase $trustedMetadata = null,
      TimestampMetadata $timestampMetadata = null
    ) {
        parent::__construct($signatureVerifier, $metadataExpiration,
          $untrustedMetadata, $trustedMetadata);
        $this->setReferencingMetadata($timestampMetadata);
    }


    public function verify()
    {
        $this->verifyNewHashes();

        // TUF-SPEC-v1.0.16 Section 5.3.2
        $this->checkSignatures();

        // TUF-SPEC-v1.0.16 Section 5.4.3
        $this->verifyNewVersion();

        if ($this->trustedMetadata) {
            static::checkRollbackAttack();
        }

        // TUF-SPEC-v1.0.16 Section 5.4.5
        static::checkFreezeAttack($this->untrustedMetadata, $this->metadataExpiration);
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

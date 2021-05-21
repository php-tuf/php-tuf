<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\TimestampMetadata;

class SnapshotVerifier extends FileInfoVerifier
{
    use ReferencedMetadataVerifierTrait;

    public function __construct(
        SignatureVerifier $signatureVerifier,
        \DateTimeImmutable $metadataExpiration,
        ?MetadataBase $trustedMetadata = null,
        TimestampMetadata $timestampMetadata = null
    ) {
        parent::__construct(
            $signatureVerifier,
            $metadataExpiration,
            $trustedMetadata
        );
        $this->setAuthorityMetadata($timestampMetadata);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(MetadataBase $untrustedMetadata): void
    {
        $this->verifyAgainstAuthorityHashes($untrustedMetadata);

        // TUF-SPEC-v1.0.16 Section 5.3.2
        $this->checkSignatures($untrustedMetadata);

        // TUF-SPEC-v1.0.16 Section 5.4.3
        $this->verifyAgainstAuthorityVersion($untrustedMetadata);

        if ($this->trustedMetadata) {
            static::checkRollbackAttack($untrustedMetadata);
        }

        // TUF-SPEC-v1.0.16 Section 5.4.5
        static::checkFreezeAttack($untrustedMetadata, $this->metadataExpiration);

        $untrustedMetadata->setIsTrusted(true);
    }

    /**
     * {@inheritdoc}
     */
    protected function checkRollbackAttack(MetadataBase $untrustedMetadata): void
    {
        parent::checkRollbackAttack($untrustedMetadata);
        $localMetaFileInfos = $this->trustedMetadata->getSigned()['meta'];
        foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
            /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $untrustedMetadata */
            if (!$untrustedMetadata->getFileMetaInfo($fileName, true)) {
                // TUF-SPEC-v1.0.16 Section 5.4.4
                // Any targets metadata filename that was listed in the trusted snapshot metadata file, if any, MUST
                // continue to be listed in the new snapshot metadata file.
                throw new RollbackAttackException("Remote snapshot metadata file references '$fileName' but this is not present in the remote file");
            }
        }
    }
}

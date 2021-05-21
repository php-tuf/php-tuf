<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\SnapshotMetadata;

class TargetsVerifier extends VerifierBase
{
    use ReferencedMetadataVerifierTrait;

    public function __construct(SignatureVerifier $signatureVerifier, \DateTimeImmutable $expiration, MetadataBase $untrustedMetadata, MetadataBase $trustedMetadata = null, SnapshotMetadata $snapshotMetadata = null)
    {
        parent::__construct(
            $signatureVerifier,
            $expiration,
            $untrustedMetadata,
            $trustedMetadata
        );
        $this->setReferrer($snapshotMetadata);
    }


    public function verify(): void
    {
        // TUF-SPEC-v1.0.16 Section 5.5.1
        $this->verifyNewHashes();

        // TUF-SPEC-v1.0.16 Section 5.5.2
        $this->checkSignatures();
        // TUF-SPEC-v1.0.16 Section 5.5.3

        $this->verifyNewVersion();
        // TUF-SPEC-v1.0.16 Section 5.5.4
        static::checkFreezeAttack($this->untrustedMetadata, $this->metadataExpiration);
        $this->untrustedMetadata->setIsTrusted(true);
    }
}

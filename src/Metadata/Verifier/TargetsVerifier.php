<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\SnapshotMetadata;

class TargetsVerifier extends VerifierBase
{
    use TrustedAuthorityTrait;

    public function __construct(SignatureVerifier $signatureVerifier, \DateTimeImmutable $expiration, MetadataBase $trustedMetadata = null, ?SnapshotMetadata $snapshotMetadata = null)
    {
        parent::__construct($signatureVerifier, $expiration, $trustedMetadata);
        if ($snapshotMetadata) {
            $this->setTrustedAuthority($snapshotMetadata);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verify(MetadataBase $untrustedMetadata): void
    {
        // TUF-SPEC-v1.0.16 Section 5.5.1
        $this->verifyAgainstAuthorityHashes($untrustedMetadata);

        // TUF-SPEC-v1.0.16 Section 5.5.2
        $this->checkSignatures($untrustedMetadata);
        // TUF-SPEC-v1.0.16 Section 5.5.3

        $this->verifyAgainstAuthorityVersion($untrustedMetadata);
        // TUF-SPEC-v1.0.16 Section 5.5.4
        static::checkFreezeAttack($untrustedMetadata, $this->metadataExpiration);
        $untrustedMetadata->setIsTrusted(true);
    }
}

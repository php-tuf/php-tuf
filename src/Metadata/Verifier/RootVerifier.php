<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;

/**
 * Verifier for root metadata.
 */
class RootVerifier extends VerifierBase
{
    /**
     * The trusted root metadata.
     *
     * @var \Tuf\Metadata\RootMetadata
     */
    protected $trustedMetadata;

    /**
     * {@inheritDoc}
     */
    public function verify(MetadataBase $untrustedMetadata): void
    {
        // *TUF-SPEC-v1.0.12 Section 5.2.3
        /** @var \Tuf\Metadata\RootMetadata $untrustedMetadata */
        $this->signatureVerifier->checkSignatures($untrustedMetadata);
        $this->signatureVerifier = SignatureVerifier::createFromRootMetadata($untrustedMetadata, true);
        $this->signatureVerifier->checkSignatures($untrustedMetadata);
        // *TUF-SPEC-v1.0.12 Section 5.2.4

        // *TUF-SPEC-v1.0.12 Section 5.2.4
        $this->checkRollbackAttack($untrustedMetadata);
    }

    /**
     * {@inheritDoc}
     */
    protected function checkRollbackAttack(MetadataBase $untrustedMetadata): void
    {
        $expectedUntrustedVersion = $this->trustedMetadata->getVersion() + 1;
        $untrustedVersion = $untrustedMetadata->getVersion();
        if ($expectedUntrustedVersion && ($untrustedMetadata->getVersion() !== $expectedUntrustedVersion)) {
            throw new RollbackAttackException("Remote 'root' metadata version \"$$untrustedVersion\" " .
              "does not the expected version \"$$expectedUntrustedVersion\"");
        }
        parent::checkRollbackAttack($untrustedMetadata);
    }

    /**
     * {@inheritdoc}
     *
     * Overridden to make public.
     *
     * After attempting to update the root metadata, the new or non-updated metadata must be checked
     * for a freeze attack. We cannot check for a freeze attack in ::verify() because when the client
     * is many root files behind, only the last version to be downloaded needs to be checked for a
     * freeze attack.
     */
    public static function checkFreezeAttack(MetadataBase $metadata, \DateTimeImmutable $expiration): void
    {
        parent::checkFreezeAttack($metadata, $expiration);
    }
}

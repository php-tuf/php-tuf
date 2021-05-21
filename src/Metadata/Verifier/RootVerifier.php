<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;

class RootVerifier extends VerifierBase
{

    /**
     * @var \Tuf\Metadata\RootMetadata
     */
    protected $untrustedMetadata;
    /**
     * @var \Tuf\Metadata\RootMetadata
     */
    protected $trustedMetadata;


    /**
     * @throws \Tuf\Exception\PotentialAttackException\RollbackAttackException
     */
    public function verify(): void
    {
        // *TUF-SPEC-v1.0.12 Section 5.2.3
        /** @var \Tuf\Metadata\RootMetadata $this->untrustedMetadata */
        $this->checkSignatures();
        $this->signatureVerifier = SignatureVerifier::createFromRootMetadata($this->untrustedMetadata, true);
        $this->checkSignatures();
        // *TUF-SPEC-v1.0.12 Section 5.2.4

        static::checkRollbackAttack();
        $this->untrustedMetadata->setIsTrusted(true);
    }

    protected function checkRollbackAttack(): void
    {
        $expectedUntrustedVersion = $this->trustedMetadata->getVersion() + 1;
        $untrustedVersion = $this->untrustedMetadata->getVersion();
        if ($expectedUntrustedVersion && ($this->untrustedMetadata->getVersion() !== $expectedUntrustedVersion)) {
            throw new RollbackAttackException("Remote 'root' metadata version \"$untrustedVersion\" " .
              "does not the expected version \"$$expectedUntrustedVersion\"");
        }
        parent::checkRollbackAttack();
    }

    /**
     * {@inheritdoc}
     *
     * Override to make public.
     *
     * After attempting to update the root metadata the new or non-upddated metadata must be checked for a freeze attack.
     * We cannot check for a freeze attack in ::verify() because when many root files behind only the last version to be downloaded
     * needs to pass a freeze attack.
     */
    public static function checkFreezeAttack(
        MetadataBase $metadata,
        \DateTimeImmutable $expiration
    ): void {
        parent::checkFreezeAttack(
            $metadata,
            $expiration
        );
    }
}

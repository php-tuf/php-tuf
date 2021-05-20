<?php


namespace Tuf\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;

class RootMetadataVerifier extends MetaDataVerifierBase
{

    /**
     * @var \Tuf\Metadata\RootMetadata
     */
    protected $untrustedMetadata;
    /**
     * @var \Tuf\Metadata\RootMetadata
     */
    protected $trustedMetadata;


    public function verify()
    {
        // *TUF-SPEC-v1.0.12 Section 5.2.3
        /** @var \Tuf\Metadata\RootMetadata $this->untrustedMetadata */
        $this->checkSignatures();
        $this->signatureVerifier = SignatureVerifier::createFromRootMetadata($this->untrustedMetadata, true);
        $this->checkSignatures();
        // *TUF-SPEC-v1.0.12 Section 5.2.4

        static::checkRollbackAttack();
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

    public static function checkFreezeAttack(
      MetadataBase $metadata,
      \DateTimeImmutable $expiration
    ): void {
        parent::checkFreezeAttack($metadata,
          $expiration);
    }


}

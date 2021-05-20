<?php


namespace Tuf\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
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

    public function __construct(
        SignatureVerifier $signatureVerifier,
        RootMetadata $trustedMetadata,
        RootMetadata $untrustedMetadata
    ) {
        parent::__construct(
          $signatureVerifier,
          $trustedMetadata,
          $untrustedMetadata
        );
    }


    public function verify()
    {
        // *TUF-SPEC-v1.0.12 Section 5.2.3
        /** @var \Tuf\Metadata\RootMetadata $this->untrustedMetadata */
        $this->signatureVerifier->checkSignatures($this->untrustedMetadata);
        $this->signatureVerifier = SignatureVerifier::createFromRootMetadata($this->untrustedMetadata, true);
        $this->signatureVerifier->checkSignatures($this->untrustedMetadata);
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
}

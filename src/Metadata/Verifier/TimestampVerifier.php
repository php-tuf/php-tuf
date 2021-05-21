<?php

namespace Tuf\Metadata\Verifier;

class TimestampVerifier extends FileInfoVerifier
{

    public function verify(): void
    {
        // § 5.3.1
        $this->checkSignatures();
        // If the timestamp or snapshot keys were rotating then the timestamp file
        // will not exist.
        if ($this->trustedMetadata) {
            // § 5.3.2.1 and 5.3.2.2
            static::checkRollbackAttack();
        }
        // § 5.3.3
        static::checkFreezeAttack($this->untrustedMetadata, $this->metadataExpiration);

        $this->untrustedMetadata->setIsTrusted(true);
    }
}

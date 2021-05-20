<?php


namespace Tuf\Verifier;

use Tuf\Metadata\TimestampMetadata;

class TimestampMetadataVerifier extends FileInfoMetadataVerifier
{

    public function verify()
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
    }
}

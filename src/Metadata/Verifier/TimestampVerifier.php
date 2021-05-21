<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Metadata\MetadataBase;

class TimestampVerifier extends FileInfoVerifier
{

    public function verify(MetadataBase $untrustedMetadata): void
    {
        // § 5.3.1
        $this->checkSignatures($untrustedMetadata);
        // If the timestamp or snapshot keys were rotating then the timestamp file
        // will not exist.
        if ($this->trustedMetadata) {
            // § 5.3.2.1 and 5.3.2.2
            static::checkRollbackAttack($untrustedMetadata);
        }
        // § 5.3.3
        static::checkFreezeAttack($untrustedMetadata, $this->metadataExpiration);

        $untrustedMetadata->setIsTrusted(true);
    }
}

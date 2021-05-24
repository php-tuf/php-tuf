<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Metadata\MetadataBase;

/**
 * Verifier for timestamp metadata.
 */
class TimestampVerifier extends FileInfoVerifier
{
    /**
     * {@inheritdoc}
     */
    public function verify(MetadataBase $untrustedMetadata): void
    {
        // § 5.3.1
        $this->signatureVerifier->checkSignatures($untrustedMetadata);
        // If the timestamp or snapshot keys were rotating then the timestamp file
        // will not exist.
        if ($this->trustedMetadata) {
            // § 5.3.2.1 and 5.3.2.2
            $this->checkRollbackAttack($untrustedMetadata);
        }
        // § 5.3.3
        static::checkFreezeAttack($untrustedMetadata, $this->metadataExpiration);
    }
}

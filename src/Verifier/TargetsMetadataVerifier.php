<?php


namespace Tuf\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\FileInfoMetadataBase;
use Tuf\Metadata\MetadataBase;

class TargetsMetadataVerifier extends MetaDataVerifierBase
{
    use ReferencedMetadataVerifierTrait;

    public function __construct(SignatureVerifier $signatureVerifier, MetadataBase $untrustedMetadata, MetadataBase $trustedMetadata = null, FileInfoMetadataBase $referencingMetadata = null)
    {
        parent::__construct(
          $signatureVerifier,
          $trustedMetadata,
          $untrustedMetadata
        );
        $this->referencingMetadata = $referencingMetadata;
    }


    public function verify()
    {
        // TODO: Implement verify() method.
    }
}

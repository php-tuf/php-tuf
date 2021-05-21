<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\Factory as MetadataFactory;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TimestampMetadata;

/**
 * Defines a class that returns verifier objects for untrusted metadata.
 */
class Factory
{
    /**
     * The trusted metadata factory.
     *
     * @var MetadataFactory
     */
    private $metadataFactory;

    /**
     * The signature verifier.
     *
     * @var SignatureVerifier
     */
    private $signatureVerifier;

    /**
     * The time beyond which untrusted metadata will be considered expired.
     *
     * @var \DateTimeImmutable
     */
    private $metadataExpiration;

    public function __construct(MetadataFactory $metadataFactory, SignatureVerifier $signatureVerifier, \DateTimeImmutable $metadataExpiration)
    {
        $this->metadataFactory = $metadataFactory;
        $this->signatureVerifier = $signatureVerifier;
        $this->metadataExpiration = $metadataExpiration;
    }

    /**
     * Gets a metadata verifier for a role.
     *
     * @param string $role
     *   The role.
     *
     * @return \Tuf\Metadata\Verifier\RootVerifier|\Tuf\Metadata\Verifier\SnapshotVerifier|\Tuf\Metadata\Verifier\TargetsVerifier|\Tuf\Metadata\Verifier\TimestampVerifier
     */
    public function getVerifier(string $role)
    {
        $trustedMetadata = $this->metadataFactory->load($role);
        switch ($role) {
            case RootMetadata::TYPE:
                $verifier = new RootVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata);
                break;
            case SnapshotMetadata::TYPE:
                /** @var \Tuf\Metadata\TimestampMetadata $timestampMetadata */
                $timestampMetadata = $this->metadataFactory->load(TimestampMetadata::TYPE);
                $verifier = new SnapshotVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata, $timestampMetadata);
                break;
            case TimestampMetadata::TYPE:
                $verifier = new TimestampVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata);
                break;
            default:
                /** @var \Tuf\Metadata\SnapshotMetadata $snapshotMetadata */
                $snapshotMetadata = $this->metadataFactory->load(SnapshotMetadata::TYPE);
                $verifier = new TargetsVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata, $snapshotMetadata);
        }
        return $verifier;
    }
}

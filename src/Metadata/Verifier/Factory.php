<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\Factory as MetadataFactory;
use Tuf\Metadata\MetadataBase;
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

    /**
     * Factory constructor.
     *
     * @param \Tuf\Metadata\Factory $metadataFactory
     * @param \Tuf\Client\SignatureVerifier $signatureVerifier
     * @param \DateTimeImmutable $metadataExpiration
     */
    public function __construct(MetadataFactory $metadataFactory, SignatureVerifier $signatureVerifier, \DateTimeImmutable $metadataExpiration)
    {
        $this->metadataFactory = $metadataFactory;
        $this->signatureVerifier = $signatureVerifier;
        $this->metadataExpiration = $metadataExpiration;
    }

    /**
     * Verifies an untrusted metadata object for a role.
     *
     * @param string $role
     * @param \Tuf\Metadata\MetadataBase $untrustedMetadata
     *
     * @throws \Tuf\Exception\PotentialAttackException\RollbackAttackException
     */
    public function verify(string $role, MetadataBase $untrustedMetadata): void
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
        $verifier->verify($untrustedMetadata);
    }
}

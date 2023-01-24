<?php

namespace Tuf\Metadata\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\Factory as MetadataFactory;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\StorageInterface;
use Tuf\Metadata\TimestampMetadata;

/**
 * Verifies untrusted metadata.
 */
class UniversalVerifier
{
    /**
     * The trusted metadata factory.
     *
     * @var MetadataFactory
     */
    private $storage;

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
     * @param \Tuf\Metadata\StorageInterface $storage
     *   The durable metadata storage.
     * @param \Tuf\Client\SignatureVerifier $signatureVerifier
     *   The signature verifier.
     * @param \DateTimeImmutable $metadataExpiration
     *   The time beyond which untrusted metadata will be considered expired.
     */
    public function __construct(StorageInterface $storage, SignatureVerifier $signatureVerifier, \DateTimeImmutable $metadataExpiration)
    {
        $this->storage = $storage;
        $this->signatureVerifier = $signatureVerifier;
        $this->metadataExpiration = $metadataExpiration;
    }

    /**
     * Verifies an untrusted metadata object for a role.
     *
     * @param string $role
     *   The metadata role (e.g. 'root', 'targets', etc.)
     * @param \Tuf\Metadata\MetadataBase $untrustedMetadata
     *   The untrusted metadata object.
     *
     * @throws \Tuf\Exception\Attack\FreezeAttackException
     * @throws \Tuf\Exception\Attack\RollbackAttackException
     * @throws \Tuf\Exception\Attack\InvalidHashException
     * @throws \Tuf\Exception\Attack\SignatureThresholdException
     */
    public function verify(string $role, MetadataBase $untrustedMetadata): void
    {
        switch ($role) {
            case RootMetadata::TYPE:
                $trustedMetadata = $this->storage->getRoot();
                $verifier = new RootVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata);
                break;
            case SnapshotMetadata::TYPE:
                $trustedMetadata = $this->storage->getSnapshot();
                /** @var \Tuf\Metadata\TimestampMetadata $timestampMetadata */
                $timestampMetadata = $this->storage->getTimestamp();
                $verifier = new SnapshotVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata, $timestampMetadata);
                break;
            case TimestampMetadata::TYPE:
                $trustedMetadata = $this->storage->getTimestamp();
                $verifier = new TimestampVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata);
                break;
            default:
                /** @var \Tuf\Metadata\SnapshotMetadata $snapshotMetadata */
                $snapshotMetadata = $this->storage->getSnapshot();
                $trustedMetadata = $this->storage->getTargets($role);
                $verifier = new TargetsVerifier($this->signatureVerifier, $this->metadataExpiration, $trustedMetadata, $snapshotMetadata);
        }
        $verifier->verify($untrustedMetadata);
        // If the verifier didn't throw an exception, we can trust this metadata.
        $untrustedMetadata->trust();
    }
}

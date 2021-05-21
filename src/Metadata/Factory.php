<?php

namespace Tuf\Metadata;

use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\Verifier\RootVerifier;
use Tuf\Metadata\Verifier\SnapshotVerifier;
use Tuf\Metadata\Verifier\TargetsVerifier;
use Tuf\Metadata\Verifier\TimestampVerifier;

/**
 * Provides methods to load value objects for trusted metadata.
 */
class Factory
{
    /**
     * The persistent storage backend for trusted metadata.
     *
     * @var \ArrayAccess
     */
    protected $storage;

    /**
     * @var \DateTimeImmutable
     */
    protected $metadataExpiration;

    /**
     * Factory constructor.
     *
     * @param \ArrayAccess $storage
     *   The persistent storage backend.
     */
    public function __construct(\ArrayAccess $storage, \DateTimeImmutable $metadataExpiration)
    {
        $this->storage = $storage;
        $this->metadataExpiration = $metadataExpiration;
    }

    /**
     * Loads a value object for trusted metadata.
     *
     * @param string $role
     *   The role to be loaded.
     *
     * @return \Tuf\Metadata\MetadataBase|null
     *   The trusted metadata for the role, or NULL if none was found.
     */
    public function load(string $role): ?MetadataBase
    {
        $fileName = "$role.json";
        if (isset($this->storage[$fileName])) {
            $json = $this->storage[$fileName];
            switch ($role) {
                case RootMetadata::TYPE:
                    $currentMetadata = RootMetadata::createFromJson($json);
                    break;
                case SnapshotMetadata::TYPE:
                    $currentMetadata = SnapshotMetadata::createFromJson($json);
                    break;
                case TimestampMetadata::TYPE:
                    $currentMetadata = TimestampMetadata::createFromJson($json);
                    break;
                default:
                    $currentMetadata = TargetsMetadata::createFromJson($json);
            }
            $currentMetadata->setIsTrusted(true);
            return $currentMetadata;
        } else {
            return null;
        }
    }

    /**
     * Gets a metadata verifier for a role.
     *
     * @param string $role
     * @param \Tuf\Client\SignatureVerifier $signatureVerifier
     *
     * @return \Tuf\Metadata\Verifier\RootVerifier|\Tuf\Metadata\Verifier\SnapshotVerifier|\Tuf\Metadata\Verifier\TargetsVerifier|\Tuf\Metadata\Verifier\TimestampVerifier
     */
    public function getVerifier(string $role, SignatureVerifier $signatureVerifier)
    {
        $trustedMetadata = $this->load($role);
        switch ($role) {
            case RootMetadata::TYPE:
                $verifier = new RootVerifier($signatureVerifier, $this->metadataExpiration, $trustedMetadata);
                break;
            case SnapshotMetadata::TYPE:
                /** @var \Tuf\Metadata\TimestampMetadata $timestampMetadata */
                $timestampMetadata = $this->load(TimestampMetadata::TYPE);
                $verifier = new SnapshotVerifier($signatureVerifier, $this->metadataExpiration, $trustedMetadata, $timestampMetadata);
                break;
            case TimestampMetadata::TYPE:
                $verifier = new TimestampVerifier($signatureVerifier, $this->metadataExpiration, $trustedMetadata);
                break;
            default:
                /** @var \Tuf\Metadata\SnapshotMetadata $snapshotMetadata */
                $snapshotMetadata = $this->load(SnapshotMetadata::TYPE);
                $verifier = new TargetsVerifier($signatureVerifier, $this->metadataExpiration, $trustedMetadata, $snapshotMetadata);
        }
        return $verifier;
    }
}

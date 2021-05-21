<?php

namespace Tuf\Verifier;

use Tuf\Client\SignatureVerifier;
use Tuf\Exception\FormatException;
use Tuf\Exception\PotentialAttackException\FreezeAttackException;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;
use Tuf\Metadata\MetadataBase;

abstract class MetadataVerifierBase
{

    /**
     * @var \Tuf\Metadata\MetadataBase
     */
    protected $trustedMetadata;

    /**
     * @var \Tuf\Metadata\MetadataBase
     */
    protected $untrustedMetadata;

    /**
     * @var \Tuf\Client\SignatureVerifier
     */
    protected $signatureVerifier;

    /**
     * @var \DateTimeImmutable
     */
    protected $metadataExpiration;


    /**
     * MetaDataVerifierBase constructor.
     *
     * @param \Tuf\Client\SignatureVerifier $signatureVerifier
     * @param \Tuf\Metadata\MetadataBase $untrustedMetadata
     * @param \Tuf\Metadata\MetadataBase|null $trustedMetadata
     */
    public function __construct(SignatureVerifier $signatureVerifier, \DateTimeImmutable $metadataExpiration, MetadataBase $untrustedMetadata, ?MetadataBase $trustedMetadata = null)
    {
        $this->signatureVerifier = $signatureVerifier;
        $this->metadataExpiration = $metadataExpiration;
        if ($trustedMetadata && !$trustedMetadata->isTrusted()) {
            throw new \LogicException("must be trusted");
        }
        $this->trustedMetadata = $trustedMetadata;
        $this->untrustedMetadata = $untrustedMetadata;
    }

    /**
     * Verify metadata according to the specification.
     *
     * All implementation should set the metadata to trusted after verification.
     */
    abstract public function verify(): void;

    /**
     * Checks for a rollback attack.
     *
     * Verifies that an incoming remote version of a metadata file is greater
     * than or equal to the last known version.
     *
     * @param \Tuf\Metadata\MetadataBase $this->trustedMetadata
     *     The locally stored metadata from the most recent update.
     * @param \Tuf\Metadata\MetadataBase $this->untrustedMetadata
     *     The latest metadata fetched from the remote repository.
     * @param integer|null $expectedRemoteVersion
     *     If not null this is expected version of remote metadata.
     *
     * @return void
     *
     * @throws \Tuf\Exception\PotentialAttackException\RollbackAttackException
     *     Thrown if a potential rollback attack is detected.
     */
    protected function checkRollbackAttack(): void
    {
        $type = $this->trustedMetadata->getType();
        $remoteVersion = $this->untrustedMetadata->getVersion();
        $localVersion = $this->trustedMetadata->getVersion();
        if ($remoteVersion < $localVersion) {
            $message = "Remote $type metadata version \"$$remoteVersion\" " .
              "is less than previously seen $type version \"$$localVersion\"";
            throw new RollbackAttackException($message);
        }
    }

    /**
     * Checks for a freeze attack.
     *
     * Verifies that metadata has not expired, and assumes a potential freeze
     * attack if it has.
     *
     * @param \Tuf\Metadata\MetadataBase $metadata
     *     The metadata for the timestamp role.
     * @param \DateTimeInterface $updaterMetadataExpirationTime
     *     The time after which metadata should be considered expired.
     *
     * @return void
     *
     * @throws FreezeAttackException
     *     Thrown if a potential freeze attack is detected.
     */
    protected static function checkFreezeAttack(MetadataBase $metadata, \DateTimeImmutable $expiration): void
    {
        $metadataExpiration = static::metadataTimestampToDatetime($metadata->getExpires());
        if ($metadataExpiration < $expiration) {
            $format = "Remote %s metadata expired on %s";
            throw new FreezeAttackException(sprintf($format, $metadata->getRole(), $metadataExpiration->format('c')));
        }
    }

    /**
     * Converts a metadata timestamp string into an immutable DateTime object.
     *
     * @param string $timestamp
     *     The timestamp string in the metadata.
     *
     * @return \DateTimeImmutable
     *     An immutable DateTime object for the given timestamp.
     *
     * @throws FormatException
     *     Thrown if the timestamp string format is not valid.
     */
    protected static function metadataTimestampToDateTime(string $timestamp): \DateTimeImmutable
    {
        $dateTime = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", $timestamp);
        if ($dateTime === false) {
            throw new FormatException($timestamp, "Could not be interpreted as a DateTime");
        }
        return $dateTime;
    }

    protected function checkSignatures()
    {
        $this->signatureVerifier->checkSignatures($this->untrustedMetadata);
    }
}

<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;
use Tuf\CanonicalJsonTrait;
use Tuf\Exception\FormatException;

/**
 * Base class for metadata.
 */
abstract class MetadataBase
{
    use CanonicalJsonTrait;
    use ConstraintsTrait;

    /**
     * Metadata type.
     *
     * @var string
     */
    protected const TYPE = '';

    /**
     * Whether the metadata has been verified and should be considered trusted.
     *
     * @var bool
     */
    private bool $isTrusted = false;

    public readonly array $signed;

    public readonly array $signatures;

    public readonly string $type;

    public readonly int $version;


    /**
     * MetadataBase constructor.
     *
     * @param array $metadata
     *   The data.
     * @param string $source
     *   The source JSON.
     */
    public function __construct(array $metadata, public readonly string $source)
    {
        ['signed' => $this->signed, 'signatures' => $this->signatures] = $metadata;
        ['_type' => $this->type, 'version' => $this->version] = $this->signed;
    }

    /**
     * Returns a normalized array version of this object for JSON encoding.
     *
     * @see ::toCanonicalJson()
     *
     * @return array
     *   A normalized array representation of this object.
     */
    protected function toNormalizedArray(): array
    {
        return $this->signed;
    }

    /**
     * Returns a canonical JSON representation of this metadata object.
     *
     * @return string
     *   The canonical JSON representation of this object.
     */
    public function toCanonicalJson(): string
    {
        return static::encodeJson($this->toNormalizedArray());
    }

    /**
     * Create an instance and also validate the decoded JSON.
     *
     * @param string $json
     *   A JSON string representing TUF metadata.
     *
     * @return static
     *   The new instance.
     *
     * @throws \Tuf\Exception\MetadataException
     *   Thrown if validation fails.
     */
    public static function createFromJson(string $json): static
    {
        $data = static::decodeJson($json);
        static::validate($data, new Collection(static::getConstraints()));
        return new static($data, $json);
    }

    /**
     * Gets the constraints for top-level metadata.
     *
     * @return \Symfony\Component\Validator\Constraint[]
     *   Array of constraints.
     */
    protected static function getConstraints(): array
    {
        return [
            'signatures' => new Required([
                new Count(['min' => 1]),
                new All([
                    new Collection([
                        'keyid' => [
                            new NotBlank(),
                            new Type('string'),
                        ],
                        'sig' => [
                            new NotBlank(),
                            new Type('string'),
                        ],
                    ]),
                ]),
                new Unique([
                    'fields' => ['keyid'],
                    'message' => 'Key IDs must be unique.',
                ]),
            ]),
            'signed' => new Required([
                new Collection(static::getSignedCollectionOptions()),
            ]),
        ];
    }

    /**
     * Gets options for the 'signed' metadata property.
     *
     * @return array
     *   An options array as expected by
     *   \Symfony\Component\Validator\Constraints\Collection::__construct().
     */
    protected static function getSignedCollectionOptions(): array
    {
        return [
            'fields' => [
                '_type' => [
                    new EqualTo(static::TYPE),
                    new Type('string'),
                ],
                'expires' => new DateTime(['value' => \DateTimeInterface::ISO8601]),
                // We only expect to work with major version 1.
                'spec_version' => [
                    new NotBlank(),
                    new Type('string'),
                    new AtLeastOneOf([
                        new IdenticalTo('1.0'),
                        new Regex('/^1\.[0-9]+\.[0-9]+$/'),
                    ]),
                ],
            ] + static::getVersionConstraints(),
            'allowExtraFields' => true,
        ];
    }

    /**
     * Get the expiration date of this metadata.
     *
     * @return \DateTimeImmutable
     *   The date and time that this metadata expires.
     *
     * @throws \Tuf\Exception\FormatException
     *   If the expiration date and time cannot be parsed.
     */
    public function getExpires(): \DateTimeImmutable
    {
        $timestamp = $this->signed['expires'];
        return \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", $timestamp) ?: throw new FormatException($timestamp, "Could not be interpreted as a DateTime");
    }

    /**
     * Gets the role for the metadata.
     *
     * @return string
     *   The type.
     */
    public function getRole(): string
    {
        // For most metadata types the 'type' and the 'role' are the same.
        // Metadata types that need to specify a different role should override
        // this method.
        return $this->type;
    }

    /**
     * Sets the metadata as trusted.
     *
     * @return $this
     */
    public function trust(): static
    {
        $this->isTrusted = true;
        return $this;
    }

    /**
     * Ensures that the metadata is trusted or the caller explicitly expects untrusted metadata.
     *
     * @param boolean $allowUntrustedAccess
     *   Whether this method should access even if the metadata is not trusted.
     */
    public function ensureIsTrusted(bool $allowUntrustedAccess = false): void
    {
        if (!$allowUntrustedAccess && !$this->isTrusted) {
            throw new \RuntimeException("Cannot use untrusted '{$this->getRole()}'. metadata.");
        }
    }
}

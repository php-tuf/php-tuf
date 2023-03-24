<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;
use Tuf\CanonicalJsonTrait;
use Tuf\Exception\MetadataException;

/**
 * Base class for metadata.
 */
abstract class MetadataBase
{
    use CanonicalJsonTrait;
    use ConstraintsTrait {
        validate as traitValidate;
    }

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


    /**
     * MetadataBase constructor.
     *
     * @param array $metadata
     *   The data.
     * @param string $sourceJson
     *   The source JSON.
     */
    public function __construct(protected array $metadata, protected string $sourceJson)
    {
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
        return $this->getSigned();
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
     * Gets the original JSON source.
     *
     * @return string
     *   The JSON source.
     */
    public function getSource(): string
    {
        return $this->sourceJson;
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
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
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
                new Type('array'),
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
            ]),
            'signed' => new Required([
                new Collection(static::getSignedCollectionOptions()),
            ]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected static function validate(array $data, Collection $constraints): void
    {
        static::traitValidate($data, $constraints);

        // The TUF spec requires that all key IDs be unique.
        // @todo Use Symfony's Unique constraint for this when at least Symfony
        //   6.1 is required.
        $keyIds = array_column($data['signatures'], 'keyid');
        if ($keyIds !== array_unique($keyIds)) {
            throw new MetadataException("Key IDs must be unique.");
        }
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
                    new Regex('/^1\.[0-9]+\.[0-9]+$/'),
                ],
            ] + static::getVersionConstraints(),
            'allowExtraFields' => true,
        ];
    }

    /**
     * Get signed.
     *
     * @return array
     *   The "signed" section of the data.
     */
    public function getSigned(): array
    {
        return $this->metadata['signed'];
    }

    /**
     * Get version.
     *
     * @return integer
     *   The version.
     */
    public function getVersion(): int
    {
        return $this->getSigned()['version'];
    }

    /**
     * Get the expires date string.
     *
     * @return string
     *   The date string.
     */
    public function getExpires(): string
    {
        return $this->getSigned()['expires'];
    }

    /**
     * Get signatures.
     *
     * @return array
     *   The "signatures" section of the data.
     */
    public function getSignatures(): array
    {
        return $this->metadata['signatures'];
    }

    /**
     * Get the metadata type.
     *
     * @return string
     *   The type.
     */
    public function getType(): string
    {
        return $this->getSigned()['_type'];
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
        return $this->getType();
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
     *
     * @return void
     */
    public function ensureIsTrusted(bool $allowUntrustedAccess = false): void
    {
        if (!$allowUntrustedAccess && !$this->isTrusted) {
            throw new \RuntimeException("Cannot use untrusted '{$this->getRole()}'. metadata.");
        }
    }
}

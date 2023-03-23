<?php

namespace Tuf\Metadata;

use DeepCopy\DeepCopy;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\JsonNormalizer;

/**
 * Base class for metadata.
 */
abstract class MetadataBase
{
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


    /**
     * MetadataBase constructor.
     *
     * @param \ArrayObject $metadata
     *   The data.
     * @param string $sourceJson
     *   The source JSON.
     */
    public function __construct(protected \ArrayObject $metadata, protected string $sourceJson, protected array $data)
    {
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
        $data = JsonNormalizer::decode($json);
        static::validate($data, new Collection(static::getConstraints()));
        return new static($data, $json, json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR));
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
     * @return iterable
     *   The "signed" section of the data.
     */
    public function getSigned(bool $asArray = false): iterable
    {
        return $asArray ? $this->data['signed'] : (new DeepCopy())->copy($this->metadata['signed']);
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
        return (new DeepCopy())->copy($this->metadata['signatures']);
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

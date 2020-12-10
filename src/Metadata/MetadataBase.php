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
use Symfony\Component\Validator\Validation;
use Tuf\Exception\MetadataException;
use Tuf\JsonNormalizer;
use function DeepCopy\deep_copy;

/**
 * Base class for metadata.
 */
abstract class MetadataBase
{
    use ConstraintsTrait;

    /**
     * The metadata.
     *
     * @var array
     */
    protected $metaData;

    /**
     * Metadata type.
     *
     * @var string
     */
    protected const TYPE = '';

    /**
     * @var string
     */
    private $sourceJson;


    /**
     * MetaDataBase constructor.
     *
     * @param \ArrayObject $metadata
     *   The data.
     * @param string $sourceJson
     *   The source JSON.
     */
    public function __construct(\ArrayObject $metadata, string $sourceJson)
    {
        $this->metaData = $metadata;
        $this->sourceJson = $sourceJson;
    }

    /**
     * Gets the original JSON source.
     *
     * @return string
     *   The JSON source.
     */
    public function getSource():string
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
    public static function createFromJson(string $json)
    {
        $data = JsonNormalizer::decode($json);
        static::validateMetaData($data);
        return new static($data, $json);
    }

    /**
     * Validates the structure of the metadata.
     *
     * @param \ArrayObject $metadata
     *   The data to validate.
     *
     * @return void
     *
     * @throws \Tuf\Exception\MetadataException
     *    Thrown if validation fails.
     */
    protected static function validateMetaData(\ArrayObject $metadata): void
    {
        $validator = Validation::createValidator();
        $collection = new Collection(static::getConstraints());
        $violations = $validator->validate($metadata, $collection);
        if (count($violations)) {
            $exceptionMessages = [];
            foreach ($violations as $violation) {
                $exceptionMessages[] = (string) $violation;
            }
            throw new MetadataException(implode(",  \n", $exceptionMessages));
        }
    }

    /**
     * Gets the constraints for top-level metadata.
     *
     * @return \Symfony\Component\Validator\Constraint[]
     *   Array of constraints.
     */
    protected static function getConstraints() : array
    {
        return [
            'signatures' => new Required([
                new Type('array'),
                new Count(['min' => 1]),
                new All([
                    new Collection([
                        'keyid' => [
                            new NotBlank(),
                            new Type(['type' => 'string']),
                        ],
                        'sig' => [
                            new NotBlank(),
                            new Type(['type' => 'string']),
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
                    new EqualTo(['value' => static::TYPE]),
                    new Type(['type' => 'string']),
                ],
                'expires' => new DateTime(['value' => \DateTimeInterface::ISO8601]),
                // We only expect to work with major version 1.
                'spec_version' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                    new Regex(['pattern' => '/^1\.[0-9]+\.[0-9]+$/']),
                ],
            ] + static::getVersionConstraints(),
            'allowExtraFields' => true,
        ];
    }

    /**
     * Get signed.
     *
     * @return \ArrayObject
     *   The "signed" section of the data.
     */
    public function getSigned():\ArrayObject
    {
        return deep_copy($this->metaData['signed']);
    }

    /**
     * Get version.
     *
     * @return integer
     *   The version.
     */
    public function getVersion() : int
    {
        return $this->getSigned()['version'];
    }

    /**
     * Get the expires date string.
     *
     * @return string
     *   The date string.
     */
    public function getExpires() : string
    {
        return $this->getSigned()['expires'];
    }

    /**
     * Get signatures.
     *
     * @return array
     *   The "signatures" section of the data.
     */
    public function getSignatures() : array
    {
        return deep_copy($this->metaData['signatures']);
    }

    /**
     * Get the metadata type.
     *
     * @return string
     *   The type.
     */
    public function getType() : string
    {
        return $this->getSigned()['_type'];
    }
}

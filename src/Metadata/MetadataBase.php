<?php


namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;
use Tuf\Exception\MetadataException;

/**
 * Base class for metadata.
 */
abstract class MetadataBase
{

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
     * MetaDataBase constructor.
     *
     * @param array $metadata
     *   The data.
     */
    public function __construct(array $metadata)
    {
        $this->metaData = $metadata;
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
     *   If validation fails.
     */
    public static function createFromJson(string $json)
    {
        $data = json_decode($json, true);
        static::validateMetaData($data);
        return new static($data);
    }

    /**
     * Validates the structure of the metadata.
     *
     * @param array $metadata
     *   The data to validate.
     *
     * @return void
     *
     * @throws \Tuf\Exception\MetadataException
     *   If validation fails.
     */
    protected static function validateMetaData(array $metadata)
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
     * Gets options as for the 'signed' metadata property.
     *
     * @return array
     *   An options array as expected by
     *   \Symfony\Component\Validator\Constraints\Collection::__construct().
     */
    protected static function getSignedCollectionOptions(): array
    {
        // Metadata date-time data follows the ISO 8601 standard.
        // The expected format of the combined date and time string
        // is "YYYY-MM-DDTHH:MM:SSZ".
        $dataPattern = '2[0-9]{3}-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9])';
        $timePattern = '(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])';
        return [
            'fields' => [
                '_type' => [
                    new EqualTo(['value' => static::TYPE]),
                    new Type(['type' => 'string']),
                ],

                'expires' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                    new Regex(['pattern' => "/^{$dataPattern}T{$timePattern}Z$/"]),
                ],
                // We only expect to work with major version 1.
                'spec_version' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                    new Regex(['pattern' => '/^1\.[0-9]+\.[0-9]+$/']),
                ],
                'version' => [
                    new NotBlank(),
                    new Type(['type' => 'integer']),
                    new GreaterThan(['value' => 0]),
                ],
            ],
            'allowExtraFields' => true,
        ];
    }

    /**
     * Get signed.
     *
     * @return array
     *   The "signed" section of the data.
     */
    public function getSigned() : array
    {
        return $this->metaData['signed'];
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
        return $this->metaData['signatures'];
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

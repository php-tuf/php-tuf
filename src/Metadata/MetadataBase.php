<?php


namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
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
     */
    public function __construct(array $metadata)
    {
        $this->metaData = $metadata;
    }

    /**
     * @param string $json
     *
     * @return static
     * @throws \Tuf\Exception\MetadataException
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
     *
     * @throws \Tuf\Exception\MetadataException
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
        return [
            'fields' => [
                '_type' => [
                  new EqualTo(['value' => static::TYPE]),
                  new Type(['type' => 'string']),
                ],
                'expires' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                ],
                'spec_version' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
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

    public function getSigned() {
        return $this->metaData['signed'];
    }

    public function getVersion() {
        return $this->getSigned()['version'];
    }

    public function getExpires() {
        return $this->getSigned()['expires'];
    }

    public function getSignatures() {
        return $this->metaData['signatures'];
    }

    public function getType() {
        return $this->getSigned()['_type'];
    }
}

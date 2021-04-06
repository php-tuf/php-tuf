<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\Exception\NotFoundException;

class TargetsMetadata extends MetadataBase
{

    /**
     * {@inheritdoc}
     */
    protected const TYPE = 'targets';

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['delegations'] = new Required([
            new Collection([
                'keys' => new Required([
                    new Type('\ArrayObject'),
                    new All([
                        static::getKeyConstraints(),
                    ]),
                ]),
                'roles' => new All([
                    new Type('\ArrayObject'),
                    new Collection([
                        'name' => [
                            new NotBlank(),
                            new Type(['type' => 'string']),
                        ],
                        'paths' => [
                            new Type(['type' => 'array']),
                            new All([
                                new Type(['type' => 'string']),
                                new NotBlank(),
                            ]),
                        ],
                        'terminating' => [
                            new Type(['type' => 'boolean']),
                        ],
                    ] + static::getKeyidsConstraints() + static::getThresholdConstraints()),
                ]),
            ]),
        ]);
        $options['fields']['targets'] = new Required([
            new All([
                new Collection([
                    'length' => [
                        new Type(['type' => 'integer']),
                        new GreaterThanOrEqual(1),
                    ],
                    'custom' => new Optional([
                        new Type('\ArrayObject'),
                    ]),
                ] + static::getHashesConstraints()),
            ]),

        ]);
        return $options;
    }

    /**
     * Returns the length, in bytes, of a specific target.
     *
     * @param string $target
     *   The target path.
     *
     * @return integer
     *   The length (size) of the target, in bytes.
     */
    public function getLength(string $target): int
    {
        return $this->getInfo($target)['length'];
    }

    /**
     * Returns the known hashes for a specific target.
     *
     * @param string $target
     *   The target path.
     *
     * @return \ArrayObject
     *   The known hashes for the object. The keys are the hash algorithm (e.g.
     *   'sha256') and the values are the hash digest.
     */
    public function getHashes(string $target): \ArrayObject
    {
        return $this->getInfo($target)['hashes'];
    }

    /**
     * Gets info about a specific target.
     *
     * @param string $target
     *   The target path.
     *
     * @return \ArrayObject
     *   The target's info.
     *
     * @throws \Tuf\Exception\NotFoundException
     *   Thrown if the target is not mentioned in this metadata.
     */
    protected function getInfo(string $target): \ArrayObject
    {
        $signed = $this->getSigned();
        if (isset($signed['targets'][$target])) {
            return $signed['targets'][$target];
        }
        throw new NotFoundException($target, 'Target');
    }
}

<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

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
                ] + static::getHashesConstraints()),
            ]),

        ]);
        return $options;
    }

    /**
     * Gets the known hashes for a target.
     *
     * @param string $target
     *   The path of the target (as known in the metadata).
     *
     * @return array
     *   The hashes for the target. The keys are the hash algorithm to use, and
     *   the values are the hash itself.
     */
    public function getHashes(string $target): array
    {
        return $this->getInfo($target)['hashes'];
    }

    /**
     * Gets the file size of a target, if known.
     *
     * @param string $target
     *   The path of the target (as known in the metadata).
     *
     * @return integer|null
     *   The size of the target in bytes, or NULL if the size is not known.
     */
    public function getLength(string $target): ?int
    {
        return $this->getInfo($target)['length'];
    }

    /**
     * Gets the signed info for a single target.
     *
     * @param string $target
     *   The path of the target (as known in the metadata).
     *
     * @return array
     *   The target's info (hashes, length, etc.)
     */
    private function getInfo(string $target): array
    {
        $signed = $this->getSigned();
        if (isset($signed['targets'][$target])) {
            return $signed['targets'][$target];
        }
        throw new \InvalidArgumentException("Unknown target: '$target'");
    }
}

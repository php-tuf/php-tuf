<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
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
                    new Type('array'),
                    new All([
                        static::getKeyConstraints(),
                    ]),
                ]),
                'roles' => new All([
                    new Type(['type' => 'array']),
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
}

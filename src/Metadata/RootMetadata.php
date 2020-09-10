<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

class RootMetadata extends MetadataBase
{

    protected const TYPE = 'root';

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['keys'] = new Required([
            new Type('array'),
            new Count(['min' => 1]),
            new All([
                new Collection([
                    'keyid_hash_algorithms' => [
                        new Count(['min' => 1]),
                        new Type(['type' => 'array']),
                        // The keys for 'hashes is not know but they all must be strings.
                        new All([
                            new Type(['type' => 'string']),
                            new NotBlank(),
                        ]),
                    ],
                    'keytype' => [
                        new Type(['type' => 'string']),
                        new NotBlank(),
                    ],
                    'keyval' => [
                        new Collection([
                            'public' => [
                                new Type(['type' => 'string']),
                                new NotBlank(),
                            ],
                        ]),
                    ],
                    'scheme' => [
                        new Type(['type' => 'string']),
                        new NotBlank(),
                    ],
                ]),
            ]),
        ]);
        $options['fields']['roles'] = new Required([
            new Type('array'),
            new Count(['min' => 1]),
            new All([
                new Collection(self::getKeyidsConstraint() + static::getThresholdConstraint()),
            ]),
        ]);
        return $options;
    }
}

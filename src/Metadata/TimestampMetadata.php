<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

class TimestampMetadata extends MetadataBase
{

    protected const TYPE = 'timestamp';

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['meta'] = new Required([
            new Type('array'),
            new Count(['min' => 1]),
            new All([
                new Collection([
                    'hashes' => [
                        new Count(['min' => 1]),
                        new Type(['type' => 'array']),
                        // The keys for 'hashes is not know but they all must be strings.
                        new All([
                            new Type(['type' => 'string']),
                            new NotBlank(),
                        ]),
                    ],
                    'length' => [
                        new Type(['type' => 'integer']),
                        new GreaterThanOrEqual(1),
                    ],
                    'version' => [
                        new Type(['type' => 'integer']),
                        new GreaterThanOrEqual(1),
                    ],
                ]),
            ]),
        ]);
        return $options;
    }
}

<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

class SnapshotMetadata extends MetadataBase
{
    use MetaFileInfoTrait;

    /**
     * {@inheritdoc}
     */
    protected const TYPE = 'snapshot';

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['meta'] = new Required([
            new Type('object'),
            new Count(['min' => 1]),
            new All([
                new Collection(
                    static::getVersionConstraints() +
                    [
                        'length' => new Optional([new Type('integer')]),
                    ]
                ),
            ]),
        ]);
        return $options;
    }
}

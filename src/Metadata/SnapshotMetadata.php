<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\Constraints\Collection;

class SnapshotMetadata extends FileInfoMetadataBase
{

    /**
     * {@inheritdoc}
     */
    public const TYPE = 'snapshot';

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['meta'] = new Required([
            new Type('\ArrayObject'),
            new Count(['min' => 1]),
            new All([
                new Collection(
                    [
                        'fields' => static::getVersionConstraints(),
                        // These fields are mentioned in the specification as optional but the Python library does not
                        // add these fields. Since we use the Python library for our fixtures we cannot create test
                        // fixtures that have these fields specified.
                        'unsupportedFields' => ['length', 'hashes'],
                        'allowExtraFields' => true,
                    ]
                ),
            ]),
        ]);
        return $options;
    }
}

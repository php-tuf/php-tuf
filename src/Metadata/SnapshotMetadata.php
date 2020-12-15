<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\Exception\MetadataException;

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
            new Type('\ArrayObject'),
            new Count(['min' => 1]),
            new All([
                new Collection(
                    [
                      'fields' => static::getVersionConstraints(),
                        'allowExtraFields' => false,
                    ]

                ),
            ]),
        ]);
        return $options;
    }
    protected static function validateMetaData(\ArrayObject $metadata): void
    {
        parent::validateMetaData($metadata);
        /** @var \ArrayAccess $signed */
        $signed = $metadata['signed'];
        if (isset($signed['meta'])) {
            foreach ($signed['meta'] as $fileName => $fileInfo) {
                if (isset($fileInfo['length'])) {
                    throw new MetadataException("The length attribute is not supported for ['meta']['$fileName'] in snapshot metadata");
                }
            }
        }
    }
}

<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

class TimestampMetadata extends MetadataBase
{
    /**
     * {@inheritdoc}
     */
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
                    'length' => [
                        new Type(['type' => 'integer']),
                        new GreaterThanOrEqual(1),
                    ],
                ] + static::getHashesConstraints() + static::getVersionConstraints()),
            ]),
        ]);
        return $options;
    }

    /**
     * Gets file information value under the 'meta' key.
     *
     * @param string $key
     *   The array key under 'meta'.
     *
     * @return mixed[]|null
     *   The file information if available or null if not set.
     */
    public function getMetaValue(string $key)
    {
        $signed = $this->getSigned();
        return $signed['meta'][$key] ?? null;
    }
}

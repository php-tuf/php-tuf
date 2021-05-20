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
    use MetaFileInfoTrait {
        checkRollbackAttack as traitCheckRollbackAttack;
    }

    /**
     * {@inheritdoc}
     */
    protected const TYPE = 'timestamp';

    public function checkRollbackAttack(MetadataBase $remoteMetadata, int $expectedRemoteVersion = null): void
    {
        parent::checkRollbackAttack($remoteMetadata, $expectedRemoteVersion);
        $this->traitCheckRollbackAttack($remoteMetadata, $expectedRemoteVersion);
    }

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
}

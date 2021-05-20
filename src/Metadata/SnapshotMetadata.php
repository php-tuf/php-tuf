<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\Constraints\Collection;
use Tuf\Exception\PotentialAttackException\RollbackAttackException;

class SnapshotMetadata extends MetadataBase
{
    use MetaFileInfoTrait {
        checkRollbackAttack as traitCheckRollbackAttack;
    }

    /**
     * {@inheritdoc}
     */
    protected const TYPE = 'snapshot';

    public function checkRollbackAttack(MetadataBase $remoteMetadata, int $expectedRemoteVersion = null): void
    {
        parent::checkRollbackAttack($remoteMetadata, $expectedRemoteVersion);
        $this->traitCheckRollbackAttack($remoteMetadata, $expectedRemoteVersion);

        $localMetaFileInfos = $this->getSigned()['meta'];
        foreach ($localMetaFileInfos as $fileName => $localFileInfo) {
            /** @var \Tuf\Metadata\SnapshotMetadata|\Tuf\Metadata\TimestampMetadata $remoteMetadata */
            $remoteFileInfo = $remoteMetadata->getFileMetaInfo($fileName, true);
            if (empty($remoteFileInfo) && static::getFileNameType($fileName) === 'targets') {
                // TUF-SPEC-v1.0.16 Section 5.4.4
                // Any targets metadata filename that was listed in the trusted snapshot metadata file, if any, MUST
                // continue to be listed in the new snapshot metadata file.
                throw new RollbackAttackException("Remote snapshot metadata file references '$fileName' but this is not present in the remote file");
            }
        }
    }

    /**
     * Gets the type for the file name.
     *
     * @param string $fileName
     *   The file name.
     *
     * @return string
     *   The type.
     */
    private static function getFileNameType(string $fileName): string
    {
        $parts = explode('.', $fileName);
        array_pop($parts);
        return array_pop($parts);
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

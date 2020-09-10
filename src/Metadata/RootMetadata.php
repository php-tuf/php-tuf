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
                static::getKeyConstraints(),
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

    /**
     * @param string $roleName
     *   The role name.
     * @return integer
     *   The threshold.
     */
    public function getRoleThreshold(string $roleName)
    {
        $this->validateRoleName($roleName);
        return $this->getSigned()['roles'][$roleName]['threshold'];
    }

    /**
     * @param string $roleName
     *   The role name.
     *
     * @return string[]
     *   The key ids.
     */
    public function getRoleKeyIds(string $roleName)
    {
        $this->validateRoleName($roleName);
        return $this->getSigned()['roles'][$roleName]['keyids'];
    }

    /**
     * @param string $roleName
     *   The role name.
     *
     * @return void
     */
    private function validateRoleName(string $roleName)
    {
        $signed = $this->getSigned();
        if (!isset($signed['roles'][$roleName])) {
            throw new \UnexpectedValueException("Role $roleName does not exist");
        }
    }


}

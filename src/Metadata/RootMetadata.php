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
                new Collection(self::getKeyidsConstraints() + static::getThresholdConstraints()),
            ]),
        ]);
        return $options;
    }

    public function getRoleNames(): array {
        return array_keys($this->getSigned()['roles']);
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

    /**
     * Gets the roles from the metadata.
     *
     * @return mixed[]
     *   An array where the keys are role names and the values arrays with the
     *   following keys:
     *   - keyids (string[]): The key ids.
     *   - threshold (int): Determines how many how may keys are need from
     *     this role for signing.
     */
    public function getRoles()
    {
        return $this->getSigned()['roles'];
    }

    /**
     * Gets the keys for the root metadata.
     *
     * @return mixed[]
     *   An array of keys information where the array keys are the key ids and
     *   the values are arrays with the following values:
     *   - keyid_hash_algorithms (string[]): The key id algorithms used.
     *   - keytype (string): The key type.
     *   - keyval (string[]): A single item array where key value is 'public'
     *     and the value is the public key.
     *   - scheme (string): The key scheme.
     */
    public function getKeys()
    {
        return $this->getSigned()['keys'];
    }
}

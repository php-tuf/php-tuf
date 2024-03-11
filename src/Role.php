<?php

namespace Tuf;

use Tuf\Metadata\ConstraintsTrait;

/**
 * Class that represents a TUF role.
 */
class Role
{
    use ConstraintsTrait;

    /**
     * Role constructor.
     *
     * @param string $name
     *   The name of the role.
     * @param int $threshold
     *   The role threshold.
     * @param array $keyIds
     *   The key IDs.
     */
    protected function __construct(public readonly string $name, public readonly int $threshold, protected array $keyIds)
    {
    }

    /**
     * Creates a role object from TUF metadata.
     *
     * @param array $roleInfo
     *   The role information from TUF metadata.
     * @param string $name
     *   The name of the role.
     *
     * @return static
     *
     * @see https://theupdateframework.github.io/specification/v1.0.32#document-formats
     */
    public static function createFromMetadata(array $roleInfo, string $name): Role
    {
        self::validate($roleInfo, static::getRoleConstraints());
        return new static(
            $name,
            $roleInfo['threshold'],
            $roleInfo['keyids']
        );
    }

    /**
     * Checks if this role's key IDs match another role's.
     *
     * @param \Tuf\Role $other
     *
     * @return bool
     */
    public function keysMatch(Role $other): bool
    {
        return $this->keyIds === $other->keyIds;
    }

    /**
     * Checks whether the given key is authorized for the role.
     *
     * @param string $keyId
     *     The key ID to check.
     *
     * @return boolean
     *     TRUE if the key is authorized for the given role, or FALSE
     *     otherwise.
     */
    public function isKeyIdAcceptable(string $keyId): bool
    {
        return in_array($keyId, $this->keyIds, true);
    }
}

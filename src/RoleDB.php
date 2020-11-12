<?php

namespace Tuf;

use Tuf\Metadata\RootMetadata;

/**
 * Represent a collection of roles and their organization.
 *
 * @see https://github.com/theupdateframework/tuf/blob/6ae3ea6d7d2aa80ba0571503a5e6c3808c44ff64/tuf/roledb.py
 */
class RoleDB
{
    /**
     * Key roles indexed by role name.
     *
     * @var \array[]
     */
    protected $roles;

    /**
     *  Creates a role database from all of the unique roles in the metadata.
     *
     * @param \Tuf\Metadata\RootMetadata $rootMetadata
     *    The root metadata.
     *
     * @return \Tuf\RoleDB
     *    The created RoleDB.
     *
     * @throws \Exception
     *     Thrown if a threshold value in the metadata is not valid.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     */
    public static function createFromRootMetadata(RootMetadata $rootMetadata)
    {
        $db = new self();
        foreach ($rootMetadata->getRoles() as $roleName => $roleInfo) {
            if ($roleName == 'root') {
                $roleInfo['version'] = $rootMetadata->getVersion();
                $roleInfo['expires'] = $rootMetadata->getExpires();
            }
            /*
            Stuff Python TUF initializes that we aren't currently using.
            $roleInfo['signatures'] = array();
            $roleInfo['signing_keyids'] = array();
            $roleInfo['partial_loaded'] = false;
            */

            // @todo Verify that testing for start of string rather than
            //     equality has a purpose.
            if (strncmp($roleName, 'targets', strlen('targets')) === 0) {
                $roleInfo['paths'] = [];
                // @todo In the spec, delegations are not part of the role
                //     structure; delegations reference roles rather than vice
                //     versa and are in a separate part of the document. Review
                //     this once https://github.com/php-tuf/php-tuf/issues/52
                //     is resolved.
                // @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
                $roleInfo['delegations'] = ['keys' => [], 'roles' => []];
            }
            $roleInfo['paths'] = [];

            $db->addRole($roleName, $roleInfo);
        }

        return $db;
    }

    /**
     * Constructs a new RoleDB object.
     */
    public function __construct()
    {
        $this->roles = [];
    }

    /**
     * Adds role metadata to the database.
     *
     * @param string $roleName
     *     The role name.
     * @param array $roleInfo
     *     An associative array of role metadata, including:
     *     - keyids: An array of authorized public key signatures for the role.
     *     - threshold: An integer for the threshold of signatures required for
     *       this role.
     *     - paths: An array of the path patterns that this role may sign.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     *     Thrown if the role already exists.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     *
     * @todo Provide more complete documentation of the structure once
     *     delgation is implemented and fixtures are regenerated. Issues:
     *     - https://github.com/php-tuf/php-tuf/issues/50
     *     - https://github.com/php-tuf/php-tuf/issues/52
     */
    public function addRole(string $roleName, array $roleInfo)
    {
        if ($this->roleExists($roleName)) {
            throw new \InvalidArgumentException('Role already exists: ' . $roleName);
        }

        $this->roles[$roleName] = $roleInfo;
    }

    /**
     * Verifies whether a given role name is stored in the role database.
     *
     * @param string $roleName
     *     The role name.
     *
     * @return boolean
     *     True if the role is found in the role database; false otherwise.
     */
    public function roleExists(string $roleName)
    {
        return !empty($this->roles[$roleName]);
    }

    /**
     * Gets the role information.
     *
     * @param string $roleName
     *    The role name.
     *
     * @return array
     *    The role information. See self::addRole() and the TUF specification
     *    for the array the structure.
     *
     * @throws \InvalidArgumentException
     *     Thrown if the role does not exist.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     */
    public function getRoleInfo(string $roleName)
    {
        if (! $this->roleExists($roleName)) {
            throw new \InvalidArgumentException("Role does not exist: $roleName");
        }

        return $this->roles[$roleName];
    }

    /**
     * Gets a list of authorized key IDs for a role.
     *
     * @param string $roleName
     *    The role name.
     *
     * @return string[]
     *    A list of key IDs that are authorized to sign for the role.
     *
     * @throws \Exception
     *    Thrown if the role does not exist.
     */
    public function getRoleKeyIds(string $roleName)
    {
        $roleInfo = $this->getRoleInfo($roleName);
        return $roleInfo['keyids'];
    }

    /**
     * Gets the threshold required for a given role.
     *
     * @param string $roleName
     *    The role name.
     *
     * @return integer
     *     The threshold number of signatures required for the role.
     *
     * @throws \InvalidArgumentException
     *     Thrown if the role does not exist.
     */
    public function getRoleThreshold(string $roleName)
    {
        if (! $this->roleExists($roleName)) {
            throw new \InvalidArgumentException("Role does not exist: $roleName");
        }

        return $this->roles[$roleName]['threshold'];
    }
}

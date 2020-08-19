<?php


namespace Tuf;

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
     * @param $rootMetadata
     *     The root metadata.
     *
     * @return \Tuf\RoleDB
     *    The created RoleDB.
     *
     * @throws \Exception
     */
    public static function createRoleDBFromRootMetadata($rootMetadata)
    {
        $db = new self();

        foreach ($rootMetadata['roles'] as $roleName => $roleInfo) {
            if ($roleName == 'root') {
                $roleInfo['version'] = $rootMetadata['version'];
                $roleInfo['expires'] = $rootMetadata['expires'];
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
                $roleInfo['delegations'] = ['keys' => [], 'roles' => []];
            }
            $roleInfo['paths'] = [];

            if ($roleInfo['threshold'] + 0 <= 0) {
                throw new \Exception("Role $roleName threshold must be an integer greater than 0");
            }

            $db->addRole($roleName, $roleInfo);
        }

        return $db;
    }

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
     *     The role metadata.
     * @throws \Exception
     *     Thrown if the role already exists.
     */
    public function addRole(string $roleName, array $roleInfo)
    {
        if ($this->roleExists($roleName)) {
            throw new \Exception('Role already exists: ' . $roleName);
        }

        $this->roles[$roleName] = $roleInfo;
    }

    /**
     * Verifies whether 'rolename' is stored in the role database.
     *
     * @param $roleName
     *     The role name.
     *
     * @return bool
     *     True if the role is found in the role database, False otherwise.
     */
    public function roleExists($roleName)
    {
        return !empty($this->roles[$roleName]);
    }

    /**
     * Gets the role information.
     *
     * @param $roleName
     *    The role name.
     *
     * @return array
     *    The role information.
     *
     * @throws \Exception
     *     Thrown if the role does not exist.
     */
    public function getRoleInfo($roleName)
    {
        if (! $this->roleExists($roleName)) {
            throw new \Exception("Role does not exist: $roleName");
        }

        return $this->roles[$roleName];
    }

    /**
     * Gets a list of key ids for a role.
     *
     * @param string $roleName
     *    The role name.
     *
     * @return string[]
     *    A list of key ids.
     *
     * @throws \Exception
     *    Thrown if the role does not exist.
     */
    public function getRoleKeyIds($roleName)
    {
        $roleInfo = $this->getRoleInfo($roleName);
        return $roleInfo['keyids'];
    }

    /**
     * Gets the threshold value of the role associated with $roleName
     *
     * @param string $roleName
     *    The role name.
     *
     * @return integer
     *
     * @throws \Exception
     */
    public function getRoleThreshold($roleName)
    {
        if (! $this->roleExists($roleName)) {
            throw new \Exception("Role does not exist: $roleName");
        }

        return $this->roles[$roleName]['threshold'];
    }
}

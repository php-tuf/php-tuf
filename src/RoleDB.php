<?php


namespace Tuf;

/**
 * Represent a collection of roles and their organization.  The caller may
 * create a collection of roles from those found in the 'root.json' metadata
 * file by calling 'create_roledb_from_root_metadata()', or individually by
 * adding roles with 'add_role()'.  There are many supplemental functions
 * included here that yield useful information about the roles contained in the
 * database, such as extracting all the parent rolenames for a specified
 * rolename, deleting all the delegated roles, retrieving role paths, etc.  The
 * Update Framework process maintains a role database for each repository.
 *
 * The role database is a dictionary conformant to
 * 'tuf.formats.ROLEDICT_SCHEMA' and has the form:
 *
 * {'repository_name': {
 * 'rolename': {'keyids': ['34345df32093bd12...'],
 * 'threshold': 1
 * 'signatures': ['abcd3452...'],
 * 'paths': ['role.json'],
 * 'path_hash_prefixes': ['ab34df13'],
 * 'delegations': {'keys': {}, 'roles': {}}}
 *
 * The 'name', 'paths', 'path_hash_prefixes', and 'delegations' dict keys are
 * optional.
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
     *  Create a role database containing all of the unique roles found in
     *  root_metadata'.
     *
     * @param $rootMetadata
     *     A dictionary conformant to 'tuf.formats.ROOT_SCHEMA'.  The roles
     *     found in the 'roles' field of 'root_metadata' is needed by this
     *     function.
     *
     * @return \Tuf\RoleDB
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
     * Add to the role database the 'roleinfo' associated with 'rolename'.
     *
     * @param $roleName
     *     An object representing the role's name, conformant to 'ROLENAME_SCHEMA'
     *     (e.g., 'root', 'snapshot', 'timestamp').
     * @param $roleInfo
     *     An object representing the role associated with 'rolename', conformant to
     *     ROLEDB_SCHEMA.  'roleinfo' has the form:
     *
     *     {'keyids': ['34345df32093bd12...'],
     *     'threshold': 1,
     *     'signatures': ['ab23dfc32']
     *     'paths': ['path/to/target1', 'path/to/target2', ...],
     *     'path_hash_prefixes': ['a324fcd...', ...],
     *     'delegations': {'keys': }
     *
     *     The 'paths', 'path_hash_prefixes', and 'delegations' dict keys are
     *     optional.
     *
     *     The 'target' role has an additional 'paths' key.  Its value is a list of
     *     strings representing the path of the target file(s).
     *
     * @throws \Exception
     */
    public function addRole($roleName, $roleInfo)
    {
        if ($this->roleExists($roleName)) {
            throw new \Exception('Role already exists: ' . $roleName);
        }

        $this->roles[$roleName] = $roleInfo;
    }

    /**
     * Verify whether 'rolename' is stored in the role database.
     *
     * @param $roleName
     *     An object representing the role's name, conformant to 'ROLENAME_SCHEMA'
     *     (e.g., 'root', 'snapshot', 'timestamp').
     *
     * @return bool
     *     True if 'rolename' is found in the role database, False otherwise.
     */
    public function roleExists($roleName)
    {
        return !empty($this->roles[$roleName]);
    }

    public function getRoleInfo($roleName)
    {
        if (! $this->roleExists($roleName)) {
            throw new \Exception("Role does not exist: $roleName");
        }

        return $this->roles[$roleName];
    }

    /**
     * Return a list of the keyids associated with 'rolename'.  Keyids are used as
     * identifiers for keys (e.g., rsa key).  A list of keyids are associated with
     * each rolename.  Signing a metadata file, such as 'root.json' (Root role),
     * involves signing or verifying the file with a list of keys identified by
     * keyid.
     *
     * @param string $roleName
     *    An object representing the role's name, conformant to
     *    'ROLENAME_SCHEMA' (e.g., 'root', 'snapshot', 'timestamp').
     *
     * @return \array[]
     *    A list of keyids.
     *
     * @throws \Exception
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
     * @return integer
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

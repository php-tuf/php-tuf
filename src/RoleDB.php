<?php


namespace Tuf;

class RoleDB
{
  /**
   * @var \array[]
   *
   * Key roles indexed by role name.
   */
    protected $roles;

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

    public function addRole($roleName, $roleInfo)
    {
        if ($this->roleExists($roleName)) {
            throw new \Exception('Role already exists: ' . $roleName);
        }

        $this->roles[$roleName] = $roleInfo;
    }

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
   * @param string $roleName
   * @return \array[]
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
   * @return int
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

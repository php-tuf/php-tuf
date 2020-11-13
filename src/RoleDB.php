<?php

namespace Tuf;

use Tuf\Exception\NotFoundException;
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
    protected $roles = [];

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

            $db->roles[$roleName] = $roleInfo;
        }

        return $db;
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
     * @throws \Tuf\Exception\NotFoundException
     *     Thrown if the role does not exist.
     *
     * @see https://github.com/theupdateframework/specification/blob/master/tuf-spec.md#4-document-formats
     */
    public function getRoleInfo(string $roleName)
    {
        if (empty($this->roles[$roleName])) {
            throw new NotFoundException($roleName, 'role');
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
}

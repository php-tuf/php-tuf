<?php


namespace Tuf;

/**
 * Class RepositoryDBCollection
 *
 * Collects KeyDBs and RoleDBs by repository name.
 */
class RepositoryDBCollection
{
    /**
     * ROLE_IX is the index of the repository array where the role DB is found.
     */
    const ROLE_IX = 0;

    /**
     * KEY_IX is the index of the repository array where the key DB is found.
     */
    const KEY_IX = 1;

    protected $dbCollection = [];

    protected static $singleton;

    public static function singleton()
    {
        if (self::$singleton == null) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function setDatabasesForRepository(KeyDB $keyDB, RoleDB $roleDB, string $repositoryName = 'default') : void
    {
        if (!empty($this->dbCollection[$repositoryName])) {
            throw new \Exception("Repository already has databases: $repositoryName");
        }

        $this->dbCollection[$repositoryName] = [
            self::ROLE_IX => $roleDB,
            self::KEY_IX => $keyDB,
        ];
    }

    /**
     * @param string $repositoryName
     * @return \array[]
     *     Array containing role database at RepositoryDBCollection::ROLE_IX,
     *     key database at RepositoryDBCollection::KEY_IX
     * @throws \Exception
     */
    public function getDatabasesForRepository(string $repositoryName = 'default')
    {
        if (empty($this->dbCollection[$repositoryName])) {
            throw new \Exception("Repository name is not known: $repositoryName");
        }

        return $this->dbCollection[$repositoryName];
    }
}

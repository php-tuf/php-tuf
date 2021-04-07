<?php


namespace Tuf;

class DelegatedRole extends Role
{

    /**
     * @var string[]
     */
    protected $paths;

    /**
     * DelegatedRole constructor.
     */
    private function __construct(string $name, int $threshold, array $keyIds, array $paths)
    {
        parent::__construct($name, $threshold, $keyIds);
        $this->paths = $paths;
    }

    public static function createFromMetadata(\ArrayObject $roleInfo, string $name = null): Role
    {
        if ($name) {
            throw new \InvalidArgumentException('$name cannont be specified for delegated roles. It must be part of the $roleInfo object.');
        }
        return new static(
            $roleInfo['name'],
            $roleInfo['threshold'],
            $roleInfo['keyids'],
            $roleInfo['paths']
        );
    }

    /**
     * @param string $target
     *   The path of the target file.
     * @param \ArrayObject $roleInfo
     *   The role information.
     *
     * @return bool
     *   True if there is path match or no path criteria is set for the role, or
     *   false otherwise.
     */
    public function matchesRolePath(string $target): bool
    {
        if ($this->paths) {
            foreach ($this->paths as $path) {
                if (fnmatch($path, $target)) {
                    return true;
                }
            }
            return false;
        }
        // If no paths are set then any target is a match.
        return true;
    }
}

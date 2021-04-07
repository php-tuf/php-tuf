<?php

namespace Tuf;

/**
 * Class that represents a TUF role.
 */
class Role
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $threshold;

    /**
     * @var array
     */
    protected $keyIds;


    /**
     * Role constructor.
     */
    private function __construct(string $name, int $threshold, array $keyIds)
    {
        $this->name = $name;
        $this->threshold = $threshold;
        $this->keyIds = $keyIds;
    }

    /**
     * Creates a role object from TUF metadata.
     *
     * @param \ArrayObject $roleInfo
     *   The role information from TUF metadata.
     *
     * @return static
     */
    public static function createFromMetadata(\ArrayObject $roleInfo, string $name): self
    {
        return new static(
            $name,
            $roleInfo['threshold'],
            $roleInfo['keyids']
        );
    }

    /**
     * Gets the role name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the threshold required.
     *
     * @return int
     *   The threshold number of signatures required for the role.
     */
    public function getThreshold(): int
    {
        return $this->threshold;
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
    public function isKeyIdAcceptable($keyId): bool
    {
        return in_array($keyId, $this->keyIds);
    }
}

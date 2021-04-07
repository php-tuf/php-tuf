<?php

namespace Tuf;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;
use Tuf\Exception\MetadataException;
use Tuf\Metadata\ConstraintsTrait;

/**
 * Class that represents a TUF role.
 */
class Role
{
    use ConstraintsTrait;

    /**
     * The role name.
     *
     * @var string
     */
    protected $name;

    /**
     * The role threshold.
     *
     * @var int
     */
    protected $threshold;

    /**
     * The key IDs.
     *
     * @var array
     */
    protected $keyIds;


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
     * @param string $name
     *   The name of the role.
     *
     * @return static
     *
     * @see @see https://github.com/theupdateframework/specification/blob/v1.0.9/tuf-spec.md#4-document-formats
     */
    public static function createFromMetadata(\ArrayObject $roleInfo, string $name): self
    {
        self::validateRoleInfo($roleInfo);
        return new static(
            $name,
            $roleInfo['threshold'],
            $roleInfo['keyids']
        );
    }

    /**
     * Validates the role info.
     *
     * @param \ArrayObject $roleInfo
     *
     * @throws \Tuf\Exception\MetadataException
     */
    private static function validateRoleInfo(\ArrayObject $roleInfo)
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($roleInfo, static::getRoleConstraints());
        if (count($violations)) {
            $exceptionMessages = [];
            foreach ($violations as $violation) {
                $exceptionMessages[] = (string) $violation;
            }
            throw new MetadataException(implode(",  \n", $exceptionMessages));
        }
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
    public function isKeyIdAcceptable(string $keyId): bool
    {
        return in_array($keyId, $this->keyIds, true);
    }
}

<?php


namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Trait with methods to provide common constraints.
 */
trait ConstraintsTrait
{

    /**
     * Gets the common hash constraints.
     *
     * @return \Symfony\Component\Validator\Constraint[][]
     *   The hash constraints.
     */
    protected static function getHashesConstraints() : array
    {
        return [
            'hashes' => [
                new Count(['min' => 1]),
                new Type('\ArrayObject'),
              // The keys for 'hashes is not know but they all must be strings.
                new All([
                    new Type(['type' => 'string']),
                    new NotBlank(),
                ]),
            ],
        ];
    }

    /**
     * Gets the common version constraints.
     *
     * @return \Symfony\Component\Validator\Constraint[][]
     *   The version constraints.
     */
    protected static function getVersionConstraints() : array
    {
        return [
            'version' => [
                new Type(['type' => 'integer']),
                new GreaterThanOrEqual(1),
            ],
        ];
    }

    /**
     * Gets the common threshold constraints.
     *
     * @return \Symfony\Component\Validator\Constraint[][]
     *   The threshold constraints.
     */
    protected static function getThresholdConstraints() : array
    {
        return [
            'threshold' => [
                new Type(['type' => 'integer']),
                new GreaterThanOrEqual(1),
            ],
        ];
    }
    /**
     * Gets the common keyids constraints.
     *
     * @return \Symfony\Component\Validator\Constraint[][]
     *   The keysids constraints.
     */
    protected static function getKeyidsConstraints() : array
    {
        return [
            'keyids' => [
                new Count(['min' => 1]),
                new Type(['type' => 'array']),
                // The keys for 'hashes is not know but they all must be strings.
                new All([
                    new Type(['type' => 'string']),
                    new NotBlank(),
                ]),
            ],
        ];
    }

    /**
     * Gets the common key Collection constraints.
     *
     * @return Collection
     *   The 'key' Collection constraint.
     */
    protected static function getKeyConstraints() : Collection
    {
        return new Collection([
            'keyid_hash_algorithms' => [
                new Count(['min' => 1]),
                new Type(['type' => 'array']),
              // The keys for 'hashes is not know but they all must be strings.
                new All([
                    new Type(['type' => 'string']),
                    new NotBlank(),
                ]),
            ],
            'keytype' => [
                new Type(['type' => 'string']),
                new NotBlank(),
            ],
            'keyval' => [
                new Type('\ArrayObject'),
                new Collection([
                    'public' => [
                        new Type(['type' => 'string']),
                        new NotBlank(),
                    ],
                ]),
            ],
            'scheme' => [
                new Type(['type' => 'string']),
                new NotBlank(),
            ],
        ]);
    }

    /**
     * Gets the role constraints.
     *
     * @return \Symfony\Component\Validator\Constraints\Collection
     *   The role constraints collection.
     */
    protected static function getRoleConstraints(): Collection
    {
        return new Collection(
          self::getKeyidsConstraints() +
          static::getThresholdConstraints()
        );
    }
}

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
     * Gets the common hash constraint.
     *
     * @return array[]
     *   The hash constraint.
     */
    protected static function getHashesConstraint() : array
    {
        return [
            'hashes' => [
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
     * Gets the common version constraint.
     *
     * @return array[]
     *   The version constraint.
     */
    protected static function getVersionConstraint() : array
    {
        return [
            'version' => [
                new Type(['type' => 'integer']),
                new GreaterThanOrEqual(1),
            ],
        ];
    }

    /**
     * Gets the common threshold constraint.
     *
     * @return array[]
     *   The threshold constraint.
     */
    protected static function getThresholdConstraint() : array
    {
        return [
            'threshold' => [
                new Type(['type' => 'integer']),
                new GreaterThanOrEqual(1),
            ],
        ];
    }
    /**
     * Gets the common keyids constraint.
     *
     * @return array[]
     *   The keysids constraint.
     */
    protected static function getKeyidsConstraint() : array
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
     * Gets the common keyids constraint.
     *
     * @return Collection
     *   The keysids constraint.
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
}

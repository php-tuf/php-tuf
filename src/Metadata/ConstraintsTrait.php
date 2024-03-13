<?php


namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;
use Tuf\Exception\MetadataException;

/**
 * Trait with methods to provide common constraints.
 */
trait ConstraintsTrait
{

    /**
     * Validates the structure of the metadata.
     *
     * @param array $data
     *   The data to validate.
     * @param \Symfony\Component\Validator\Constraints\Collection $constraints
     *   Th constraints collection for validation.
     *
     * @throws \Tuf\Exception\MetadataException
     *    Thrown if validation fails.
     */
    protected static function validate(array $data, Collection $constraints): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($data, $constraints);
        if (count($violations)) {
            $exceptionMessages = [];
            foreach ($violations as $violation) {
                $exceptionMessages[] = (string) $violation;
            }
            throw new MetadataException(implode(",  \n", $exceptionMessages));
        }
    }

    /**
     * Gets the common hash constraints.
     *
     * @return \Symfony\Component\Validator\Constraint[][]
     *   The hash constraints.
     */
    protected static function getHashesConstraints(): array
    {
        return [
            'hashes' => [
                new Count(['min' => 1]),
              // The keys for 'hashes is not know but they all must be strings.
                new All([
                    new Type('string'),
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
    protected static function getVersionConstraints(): array
    {
        return [
            'version' => [
                new Type('integer'),
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
    protected static function getThresholdConstraints(): array
    {
        return [
            'threshold' => [
                new Type('integer'),
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
    protected static function getKeyidsConstraints(): array
    {
        return [
            'keyids' => [
                new Count(['min' => 1]),
                // The keys for 'hashes is not know but they all must be strings.
                new All([
                    new Type('string'),
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
    protected static function getKeyConstraints(): Collection
    {
        return new Collection([
            'keytype' => [
                new Type('string'),
                new IdenticalTo('ed25519'),
            ],
            'keyval' => [
                new Collection([
                    'public' => [
                        new Type('string'),
                        new NotBlank(),
                    ],
                ]),
            ],
            'scheme' => [
                new Type('string'),
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
            static::getKeyidsConstraints() +
            static::getThresholdConstraints()
        );
    }
}

<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\DelegatedRole;
use Tuf\Exception\NotFoundException;
use Tuf\Key;

class TargetsMetadata extends MetadataBase
{

    /**
     * {@inheritdoc}
     */
    public const TYPE = 'targets';

    /**
     * The role name if different from the type.
     *
     * @var string
     */
    private ?string $role;

    /**
     * {@inheritdoc}
     *
     * @param string|null $roleName
     *   The role name if not the same as the type.
     */
    public static function createFromJson(string $json, ?string $roleName = null): static
    {
        $newMetadata = parent::createFromJson($json);
        $newMetadata->role = $roleName;
        return $newMetadata;
    }

    /**
     * {@inheritDoc}
     */
    protected function toNormalizedArray(): array
    {
        $normalized = parent::toNormalizedArray();

        foreach ($normalized['targets'] as $path => $target) {
            // Custom target info should always encode to an object, even if
            // it's empty.
            if (array_key_exists('custom', $target) && empty($target['custom'])) {
                $normalized['targets'][$path]['custom'] = new \stdClass();
            }
        }

        // Ensure that these will encode as objects even if they're empty.
        if (empty($normalized['targets'])) {
            $normalized['targets'] = new \stdClass();
        }
        if (array_key_exists('delegations', $normalized) && empty($normalized['delegations']['keys'])) {
            $normalized['delegations']['keys'] = new \stdClass();
        }

        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['delegations'] = new Optional([
            new Collection([
                'keys' => new Required([
                    new All([
                        static::getKeyConstraints(),
                    ]),
                ]),
                'roles' => new Required([
                    new All([
                        new Collection(
                            [
                                'name' => [
                                    new NotBlank(),
                                    new Type('string'),
                                ],
                                'paths' => new Optional([
                                    new All([
                                        new Type('string'),
                                        new NotBlank(),
                                    ]),
                                ]),
                                'path_hash_prefixes' => new Optional([
                                    new All([
                                        new Type('string'),
                                        new NotBlank(),
                                    ]),
                                ]),
                                'terminating' => [
                                    new Type('boolean'),
                                ],
                            ] + static::getKeyidsConstraints() + static::getThresholdConstraints(),
                        ),
                    ]),
                    new Unique(
                        message: 'Delegated role names must be unique.',
                        fields: ['name'],
                    ),
                ]),
            ]),
        ]);
        $options['fields']['targets'] = new Required([
            new All([
                new Collection([
                    'length' => [
                        new Type('integer'),
                        new GreaterThanOrEqual(1),
                    ],
                    'custom' => new Optional([
                        new Type('array'),
                    ]),
                ] + static::getHashesConstraints()),
            ]),

        ]);
        return $options;
    }

    /**
     * Returns the length, in bytes, of a specific target.
     *
     * @param string $target
     *   The target path.
     *
     * @return integer
     *   The length (size) of the target, in bytes.
     */
    public function getLength(string $target): int
    {
        return $this->getInfo($target)['length'];
    }

    /**
     * {@inheritdoc}
     */
    public function getRole(): string
    {
        return $this->role ?? $this->type;
    }

    /**
     * Returns the known hashes for a specific target.
     *
     * @param string $target
     *   The target path.
     *
     * @return array
     *   The known hashes for the object. The keys are the hash algorithm (e.g.
     *   'sha256') and the values are the hash digest.
     */
    public function getHashes(string $target): array
    {
        return $this->getInfo($target)['hashes'];
    }

    /**
     * Determines if a target is specified in the current metadata.
     *
     * @param string $target
     *   The target path.
     *
     * @return bool
     *   True if the target is specified, or false otherwise.
     */
    public function hasTarget(string $target): bool
    {
        try {
            $this->getInfo($target);
            return true;
        } catch (NotFoundException $exception) {
            return false;
        }
    }

    /**
     * Gets info about a specific target.
     *
     * @param string $target
     *   The target path.
     *
     * @return array
     *   The target's info.
     *
     * @throws \Tuf\Exception\NotFoundException
     *   Thrown if the target is not mentioned in this metadata.
     */
    protected function getInfo(string $target): array
    {
        $signed = $this->signed;
        if (isset($signed['targets'][$target])) {
            return $signed['targets'][$target];
        }
        throw new NotFoundException($target, 'Target');
    }

    /**
     * Gets the delegated keys if any.
     *
     * @return \Tuf\Key[]
     *   The delegated keys.
     */
    public function getDelegatedKeys(): array
    {
        $keys = [];
        foreach ($this->signed['delegations']['keys'] ?? [] as $keyId => $keyInfo) {
            $keys[$keyId] = Key::createFromMetadata($keyInfo);
        }
        return $keys;
    }

    /**
     * Gets the delegated roles if any.
     *
     * @return \Tuf\DelegatedRole[]
     *   The delegated roles.
     */
    public function getDelegatedRoles(): array
    {
        $roles = [];
        foreach ($this->signed['delegations']['roles'] ?? [] as $roleInfo) {
            $role = DelegatedRole::createFromMetadata($roleInfo);
            $roles[$role->name] = $role;
        }
        return $roles;
    }

    /**
     * Get delegated roles, pre-filtered by target.
     *
     * Delegated roles are also filtered so that terminating roles end lookup.
     * This method duplicates some logic in DelegatedRole::matchesPath() so
     * that thousands of delegated roles can be checked for applicability prior
     * to instantiating thousands of objects.
     *
     * @param string $target
     *   The target to filter by.
     *
     * @return \Tuf\DelegatedRole[]
     *   The delegated roles.
     */
    public function getDelegatedRolesForTarget($target): array
    {
        $targetHash = hash('sha256', $target);
        $roles = [];
        foreach ($this->signed['delegations']['roles'] ?? [] as $roleInfo) {
            if ($this->roleMatchesTarget($roleInfo, $target, $targetHash) {
                $role = new DelegatedRole($roleInfo);
                $roles[] = new DelegatedRole($roleInfo);
                if ($role->terminating) {
                    break;
                }
            }
        }
        return $roles;
    }

    private function roleMatchesTarget(array $roleInfo, string $target, string $targetHash): bool
    {
        $targetHash = hash('sha256', $target);
        foreach ($roleInfo['path_hash_prefixes'] ?? [] as $prefix) {
            if (str_starts_with($targetHash, $prefix)) {
                return true;
            }
        }
        foreach ($roleInfo['paths'] ?? [] as $path) {
            if (fnmatch($path, $target)) {
                return true;
            }
        }
        return false;
    }

}

<?php


namespace Tuf;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Tuf\Exception\MetadataException;

/**
 * Class that represents a Delegated TUF role.
 */
class DelegatedRole extends Role
{
    /**
     * DelegatedRole constructor.
     *
     * @param string $name
     * @param int $threshold
     * @param array $keyIds
     * @param array|null $paths
     * @param array|null $pathHashPrefixes
     * @param bool $terminating
     */
    private function __construct(string $name, int $threshold, array $keyIds, protected ?array $paths, protected ?array $pathHashPrefixes, public readonly bool $terminating)
    {
        parent::__construct($name, $threshold, $keyIds);
    }

    public static function createFromMetadata(iterable $roleInfo, ?string $name = null): Role
    {
        $roleConstraints = static::getRoleConstraints();
        $roleConstraints->fields += [
            'name' => new Required(
                [
                    new Type('string'),
                    new NotBlank(),
                ]
            ),
            'terminating' => new Required(new Type('boolean')),
            // `paths` is mutually exclusive with `path_hash_prefixes`.
            // @see ::validate()
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
        ];
        static::validate($roleInfo, $roleConstraints);
        return new static(
            $roleInfo['name'],
            $roleInfo['threshold'],
            $roleInfo['keyids'],
            $roleInfo['paths'] ?? null,
            $roleInfo['path_hash_prefixes'] ?? null,
            $roleInfo['terminating']
        );
    }

    /**
     * {@inheritDoc}
     */
    protected static function validate(array $data, Collection $constraints): void
    {
        parent::validate($data, $constraints);

        // Either `paths` or `path_hash_prefixes` MUST be specified, but not
        // both.
        if (!(array_key_exists('paths', $data) xor array_key_exists('path_hash_prefixes', $data))) {
            throw new MetadataException('Either paths or path_hash_prefixes must be specified, but not both.');
        }
    }
}

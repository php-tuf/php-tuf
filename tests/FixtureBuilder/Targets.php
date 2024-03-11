<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

/**
 * A class that can be used to create TUF targets metadata.
 *
 * This can also be used to create delegated targets metadata, since they are
 * just regular targets metadata with a different role name.
 */
final class Targets extends Payload
{
    public ?array $paths = null;

    public ?array $pathHashPrefixes = null;

    public bool $terminating = false;

    /**
     * The targets this role will sign.
     *
     * The keys are the names of the targets, and the values are their full
     * paths in the filesystem.
     *
     * @var string[]
     */
    private array $targets = [];

    public function __construct(Root|self $keyRing, Snapshot $parent, string $name = 'targets', mixed ...$arguments)
    {
        // Only the top-level `targets` role is signed by the root role. All
        // delegated roles (which have names other than `targets`) are signed by
        // their parent.
        if ($keyRing instanceof Root) {
            assert($name === 'targets');
        }
        parent::__construct($name, $keyRing, $parent, ...$arguments);
    }

    /**
     * Delegates to a new targets role.
     *
     * @param string $name
     *   The name of the delegated role.
     *
     * @return self
     */
    public function delegate(string $name): self
    {
        return new self($this, $this->parent, $name, $this->expires, [
            Key::fromStaticList(),
        ]);
    }

    /**
     * Adds a target to be signed by this role.
     *
     * @param string $path
     *   The path of the file to sign.
     * @param string|null $name
     *   The name of the target. If not passed, will be the file's base name.
     */
    public function add(string $path, ?string $name = null): void
    {
        assert(is_file($path));

        $name ??= basename($path);
        $this->targets[$name] = $path;

        $this->markAsDirty();
        // Because we have changed, the snapshot will need to be updated too.
        $this->parent->markAsDirty();

        // If we are a delegated role, the top-level targets role also needs to
        // be updated when we've changed.
        if ($this->name !== 'targets') {
            $this->parent->payloads['targets']?->markAsDirty();
        }
    }

    protected function toArray(): array
    {
        $data = parent::toArray();

        $data['_type'] = 'targets';

        /** @var self $delegation */
        foreach ($this->payloads as $delegation) {
            $role = [
                'name' => $delegation->name,
                'threshold' => $delegation->threshold,
                'terminating' => $delegation->terminating,
            ];
            if (is_array($delegation->paths)) {
                $role['paths'] = $delegation->paths;
            }
            if (is_array($delegation->pathHashPrefixes)) {
                $role['path_hash_prefixes'] = $delegation->pathHashPrefixes;
            }

            foreach ($delegation->signingKeys as $key) {
                $id = $key->id();
                $data['delegations']['keys'][$id] = $key->toArray();
                $role['keyids'][] = $id;
            }
            $data['delegations']['roles'][] = $role;
        }

        foreach ($this->targets as $name => $path) {
            $data['targets'][$name] = [
                'hashes' => [
                    'sha256' => hash_file('sha256', $path),
                    'sha512' => hash_file('sha512', $path),
                ],
                'length' => filesize($path),
            ];
            if ($this->name !== 'targets') {
                $data['targets'][$name]['custom'] = (object) [];
            }
        }

        $data += [
            'delegations' => [
                'keys' => (object) [],
                'roles' => [],
            ],
            'targets' => (object) [],
        ];
        return $data;
    }
}

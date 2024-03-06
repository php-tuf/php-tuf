<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Targets extends Role
{
    public ?array $paths = null;

    public ?array $pathHashPrefixes = null;

    public bool $terminating = false;

    /**
     * @var self[]
     */
    private array $delegations = [];

    private array $targets = [];

    public function __construct(
      public readonly string $name = 'targets',
      mixed ...$arguments,
    ) {
        parent::__construct(...$arguments);
    }

    public function add(string $path, ?string $name = null): self
    {
        assert(is_file($path));

        $name ??= basename($path);
        $this->targets[$name] = $path;

        return $this;
    }

    public function addDelegation(self $role): self
    {
        $this->delegations[$role->name] = $role;
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        foreach ($this->delegations as $delegation) {
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

            foreach ($delegation->keys as $key) {
                $id = $key->id();
                $data['delegations']['keys'][$id] = $key->toArray();
                $role['keyids'][] = $id;
            }
            $data['delegations']['roles'][] = $role;
        }
        $data += [
          'delegations' => [
            'keys' => (object) [],
            'roles' => [],
          ],
        ];

        $data['targets'] = [];
        foreach ($this->targets as $name => $path) {
            assert(is_file($path));

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

        $data['_type'] = 'targets';
        return $data;
    }
}

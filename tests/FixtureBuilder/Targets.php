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

    public array $targets = [];

    public function __construct(\DateTimeImmutable $expires, array $keys = [], public readonly string $name = 'targets')
    {
        parent::__construct($expires, $keys);
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

        foreach ($this->targets as $name => $path) {
            assert(is_file($path));

            $data['targets'][$name] = [
              'hashes' => [
                'sha256' => hash_file('sha256', $path),
              ],
              'length' => filesize($path),
            ];
        }

        $data['_type'] = 'targets';
        return $data;
    }
}
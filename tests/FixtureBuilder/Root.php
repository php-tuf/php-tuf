<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Root extends Role
{
    public ?bool $consistentSnapshot = null;

    private array $roles = [];

    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => 'root',
            default => parent::__get($name),
        };
    }

    public function addRole(Snapshot|Targets|Timestamp $role): static
    {
        if ($role instanceof Targets) {
            assert($role->name === 'targets');
        }
        $this->roles[$role->name] = $role;
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        foreach ([$this, ...$this->roles] as $role) {
            $data['roles'][$role->name]['threshold'] = $role->threshold;

            foreach ($role->keys as $key) {
                if (in_array($key, $role->revokedKeys, true)) {
                    continue;
                }
                $id = $key->id();
                $data['keys'][$id] = $key->toArray();
                $data['roles'][$role->name]['keyids'][] = $id;
            }
        }

        if (is_bool($this->consistentSnapshot)) {
            $data['consistent_snapshot'] = $this->consistentSnapshot;
        }
        $data['_type'] = 'root';

        return $data;
    }
}

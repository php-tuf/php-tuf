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

    public function addRole(Role $role): static
    {
        assert (! in_array($role, $this->roles, true));
        $this->roles[] = $role;
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        foreach ([$this, ...$this->roles] as $role) {
            $data['roles'][$role->name]['threshold'] = $role->threshold;

            foreach ($role->keys as $key) {
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

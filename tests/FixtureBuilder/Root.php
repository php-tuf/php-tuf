<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Root extends Role
{
    public ?bool $consistentSnapshot = null;

    private array $roles = [];

    protected ?string $name = 'root';

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
            $name = basename($role->fileName(false), '.json');
            $data['roles'][$name]['threshold'] = $role->threshold;

            foreach ($role->keys as $key) {
                $id = $key->id();
                $data['keys'][$id] = $key->toArray();
                $data['roles'][$name]['keyids'][] = $id;
            }
        }

        if (is_bool($this->consistentSnapshot)) {
            $data['consistent_snapshot'] = $this->consistentSnapshot;
        }
        $data['_type'] = 'root';

        return $data;
    }
}

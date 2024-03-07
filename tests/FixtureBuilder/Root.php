<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Root extends Payload
{
    public ?bool $consistentSnapshot = null;

    private array $payloads = [];

    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => 'root',
        };
    }

    public function addRole(Snapshot|Targets|Timestamp $role): static
    {
        if ($role instanceof Targets) {
            assert($role->name === 'targets');
        }
        $this->payloads[$role->name] = $role;
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        /** @var Payload $role */
        foreach ([$this, ...$this->payloads] as $role) {
            $data['roles'][$role->name]['threshold'] = $role->threshold;

            foreach ($role->signingKeys as $key) {
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

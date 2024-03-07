<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Root extends Payload
{
    public ?bool $consistentSnapshot = null;

    public function __construct(mixed ...$arguments)
    {
        parent::__construct(null, 'root', ...$arguments);
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

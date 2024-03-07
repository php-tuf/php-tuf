<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Snapshot extends MetadataAuthorityPayload
{
    public function __construct(Root $signer, mixed ...$arguments)
    {
        parent::__construct($signer, ...$arguments);
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => 'snapshot',
        };
    }

    public function addRole(Targets $role): static
    {
        $this->meta[] = $role;
        return $this;
    }

    public function addKey(Key $key = null): static
    {
        parent::addKey($key);
        $this->signer->isDirty = true;
        return $this;
    }

    public function revokeKey(int $which): void
    {
        parent::revokeKey($which);
        $this->signer->isDirty = true;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        $data['_type'] = 'snapshot';
        return $data;
    }
}

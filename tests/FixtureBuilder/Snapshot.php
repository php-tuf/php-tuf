<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Snapshot extends MetadataAuthorityRole
{
    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => 'snapshot',
            default => parent::__get($name),
        };
    }

    public function addRole(Targets $role): static
    {
        $this->meta[] = $role;
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        $data['_type'] = 'snapshot';
        return $data;
    }
}

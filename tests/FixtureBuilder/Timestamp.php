<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityRole
{
    public function __construct(Snapshot $snapshot, mixed ...$arguments)
    {
        parent::__construct(...$arguments);
        $this->meta = [$snapshot];
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => 'timestamp',
            default => parent::__get($name),
        };
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        $data['_type'] = 'timestamp';
        return $data;
    }
}

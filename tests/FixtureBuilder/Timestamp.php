<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityPayload
{
    public function __construct(Root $signer, Snapshot $snapshot, mixed ...$arguments)
    {
        parent::__construct($signer, ...$arguments);
        $this->meta = [$snapshot];
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => 'timestamp',
        };
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

        $data['_type'] = 'timestamp';
        return $data;
    }
}

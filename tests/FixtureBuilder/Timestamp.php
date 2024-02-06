<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityRole
{
    public function setSnapshot(Snapshot $snapshot): self
    {
        $this->meta = [$snapshot];
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        $data['_type'] = 'timestamp';
        return $data;
    }

    public function fileName(): string
    {
        return $this->version . '.timestamp.json';
    }
}

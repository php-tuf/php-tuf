<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityRole
{
    protected ?string $name = 'timestamp';

    public function setSnapshot(Snapshot $snapshot): static
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
}

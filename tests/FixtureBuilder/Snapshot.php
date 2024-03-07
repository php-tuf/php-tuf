<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Snapshot extends MetadataAuthorityPayload
{
    public function __construct(Root $signer, mixed ...$arguments)
    {
        parent::__construct($signer, 'snapshot', ...$arguments);
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

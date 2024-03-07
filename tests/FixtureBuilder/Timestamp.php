<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityPayload
{
    public function __construct(Root $signer, Snapshot $snapshot, mixed ...$arguments)
    {
        parent::__construct($signer, 'timestamp', ...$arguments);
        $this->meta = [$snapshot];
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

        $data['_type'] = 'timestamp';
        return $data;
    }
}

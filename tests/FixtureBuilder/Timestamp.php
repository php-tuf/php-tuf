<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityPayload
{
    public function __construct(Root $signer, mixed ...$arguments)
    {
        parent::__construct($signer, 'timestamp', ...$arguments);
    }

    protected function addPayload(Payload $payload): void
    {
        assert($payload instanceof Snapshot);

        $this->payloads = [];
        parent::addPayload($payload);
    }
}

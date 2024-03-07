<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityPayload
{
    public function __construct(Root $keyRing, mixed ...$arguments)
    {
        parent::__construct('timestamp', $keyRing, null, ...$arguments);
    }

    protected function watch(Payload $payload): void
    {
        assert($payload instanceof Snapshot && empty($this->payloads));
        parent::watch($payload);
    }
}

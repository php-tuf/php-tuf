<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Timestamp extends MetadataAuthorityPayload
{
    public function __construct(Root $keyRing, mixed ...$arguments)
    {
        parent::__construct('timestamp', $keyRing, null, ...$arguments);
    }

    /**
     * {@inheritDoc}
     */
    protected function watch(Payload $payload): void
    {
        // The timestamp role only watches for changes in the snapshot role, of
        // which there is only one.
        assert($payload instanceof Snapshot && empty($this->payloads));
        parent::watch($payload);
    }
}

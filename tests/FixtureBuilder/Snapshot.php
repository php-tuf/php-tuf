<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Snapshot extends MetadataAuthorityPayload
{
    public function __construct(Root $keyRing, Timestamp $parent, mixed ...$arguments)
    {
        parent::__construct('snapshot', $keyRing, $parent, ...$arguments);
    }

    protected function watch(Payload $payload): void
    {
        assert($payload instanceof Targets);
        parent::watch($payload);

        $this->parent->markAsDirty();
    }

    public function addKey(): void
    {
        parent::addKey();
        $this->parent->markAsDirty();
    }

    public function revokeKey(int $which): void
    {
        parent::revokeKey($which);
        $this->parent->markAsDirty();
    }
}

<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

/**
 * A class that can be used to create snapshot metadata.
 */
final class Snapshot extends MetadataAuthorityPayload
{
    public function __construct(Root $keyRing, Timestamp $parent, mixed ...$arguments)
    {
        parent::__construct('snapshot', $keyRing, $parent, ...$arguments);
    }

    /**
     * {@inheritDoc}
     */
    protected function watch(Payload $payload): void
    {
        // The snapshot role only watches for changes in targets roles.
        assert($payload instanceof Targets);
        parent::watch($payload);

        // Because we've changed, the timestamp role will need to be updated as
        // well.
        $this->parent->markAsDirty();
    }

    /**
     * {@inheritDoc}
     */
    public function addKey(): void
    {
        parent::addKey();
        // Because we've changed, the timestamp role will need to be updated as
        // well.
        $this->parent->markAsDirty();
    }

    /**
     * {@inheritDoc}
     */
    public function revokeKey(int $which): void
    {
        parent::revokeKey($which);
        // Because we've changed, the timestamp role will need to be updated as
        // well.
        $this->parent->markAsDirty();
    }
}

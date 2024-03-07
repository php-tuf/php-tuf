<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Snapshot extends MetadataAuthorityPayload
{
    public function __construct(Root $signer, Timestamp $parent, mixed ...$arguments)
    {
        parent::__construct($signer, 'snapshot', ...$arguments);

        $parent->addPayload($this);
    }

    public function addPayload(Payload $payload): void
    {
        assert($payload instanceof Targets);
        parent::addPayload($payload);
    }
}

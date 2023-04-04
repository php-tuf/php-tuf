<?php

namespace Tuf\Tests\TestHelpers;

use Tuf\Client\Repository;
use Tuf\Metadata\TargetsMetadata;

/**
 * Allows mocked metadata objects to be returned from the server in tests.
 */
class TestRepository extends Repository
{
    /**
     * The mocked targets metadata, keyed by role name and version number.
     *
     * @var \Tuf\Metadata\TargetsMetadata[][]
     *
     * @see ::getTargets()
     */
    public array $targets = [];

    /**
     * {@inheritDoc}
     */
    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = null): TargetsMetadata
    {
        if (!empty($this->targets[$role])) {
            $version ??= array_key_last($this->targets[$role]);
            return $this->targets[$role][$version];
        }
        return parent::getTargets($version, $role, $maxBytes);
    }
}

<?php

namespace Tuf\Tests\TestHelpers;

use Tuf\Client\Repository;
use Tuf\Metadata\TargetsMetadata;

class TestRepository extends Repository
{
    public array $targets = [];

    /**
     * {@inheritDoc}
     */
    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = self::MAX_BYTES): TargetsMetadata
    {
        if (!empty($this->targets[$role])) {
            $version ??= array_key_last($this->targets[$role]);
            return $this->targets[$role][$version];
        }
        return parent::getTargets($version, $role, $maxBytes);
    }
}

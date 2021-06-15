<?php

namespace Tuf;

use Tuf\Metadata\MetadataBase;

/**
 * Defines an interface to persistent and load trusted TUF metadata.
 */
interface StorageInterface
{
    public function load(string $role): ?MetadataBase;

    public function save(MetadataBase $metadata): void;
}

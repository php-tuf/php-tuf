<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

abstract class MetadataAuthorityRole extends Role
{
    protected array $meta = [];

    public bool $withHashes = true;

    public bool $withLength = true;

    public function getSigned(): array
    {
        $data = parent::getSigned();

        foreach ($this->meta as $meta) {
            $name = $meta->fileName(false);
            $data['meta'][$name]['version'] = $meta->version;

            if ($this->withHashes || $this->withLength) {
                $meta = (string) $meta;
            }
            if ($this->withHashes) {
                $data['meta'][$name]['hashes']['sha256'] = hash('sha256', $meta);
            }
            if ($this->withLength) {
                $data['meta'][$name]['length'] = mb_strlen($meta);
            }
        }
        return $data;
    }
}

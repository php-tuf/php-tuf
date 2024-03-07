<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

abstract class MetadataAuthorityPayload extends Payload
{
    public bool $withHashes = true;

    public bool $withLength = true;

    public function getSigned(): array
    {
        $data = parent::getSigned();

        foreach ($this->payloads as $name => $payload) {
            $name .= '.' . static::FILE_EXTENSION;
            $data['meta'][$name]['version'] = $payload->version;

            if ($this->withHashes || $this->withLength) {
                $payload = (string) $payload;
            }
            if ($this->withHashes) {
                $data['meta'][$name]['hashes'] = [
                  'sha256' => hash('sha256', $payload),
                  'sha512' => hash('sha512', $payload),
                ];
            }
            if ($this->withLength) {
                $data['meta'][$name]['length'] = mb_strlen($payload);
            }
        }
        return $data;
    }
}

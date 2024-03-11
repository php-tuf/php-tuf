<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

/**
 * Defines a payload which contains authoritative information about another one.
 *
 * This is only used by the Timestamp and Snapshot payloads, since those are the
 * only two kinds of TUF metadata which are authoritative about other metadata.
 */
abstract class MetadataAuthorityPayload extends Payload
{
    /**
     * Whether to include the hashes of the other metadata.
     *
     * @var bool
     */
    public bool $withHashes = true;

    /**
     * Whether to include the file sizes of the other metadata.
     *
     * @var bool
     */
    public bool $withLength = true;

    protected function toArray(): array
    {
        $data = parent::toArray();

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

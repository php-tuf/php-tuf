<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

use Tuf\CanonicalJsonTrait;

/**
 */
abstract class Payload implements \Stringable
{
    use CanonicalJsonTrait;

    public int $threshold = 1;

    public int $version = 1;

    public bool $isDirty = false;

    protected array $revokedKeys = [];

    protected array $payloads = [];

    final public const FILE_EXTENSION = 'json';

    public function __construct(
      public readonly string $name,
      protected readonly ?Payload $keyRing,
      protected readonly ?Payload $parent,
      public \DateTimeImmutable $expires,
      protected array $signingKeys = [],
    ) {
        $keyRing?->watch($this);
        $parent?->watch($this);
    }

    final public function addKey(): void
    {
        $this->signingKeys[] = new Key;

        $this->isDirty = true;
        if ($this->keyRing) {
            $this->keyRing->isDirty = true;
        }
    }

    final public function revokeKey(int $which): void
    {
        array_push($this->revokedKeys, ...array_splice($this->signingKeys, $which, 1));

        $this->isDirty = true;
        if ($this->keyRing) {
            $this->keyRing->isDirty = true;
        }
    }

    protected function watch(Payload $payload): void
    {
        $this->payloads[$payload->name] = $payload;
        $this->isDirty = true;
    }

    public function __toString(): string
    {
        $payload = $this->toArray();

        $data = [
          'signatures' => array_map(fn (Key $key) => $key->sign($payload), [
            ...$this->signingKeys,
            ...$this->revokedKeys,
          ]),
          'signed' => $payload,
        ];
        return self::encodeJson($data, JSON_PRETTY_PRINT);
    }

    public function toArray(): array
    {
        return [
          'expires' => $this->expires->format('Y-m-d\TH:i:sp'),
          'spec_version' => '1.0.0',
          'version' => $this->version,
          '_type' => $this->name,
        ];
    }
}

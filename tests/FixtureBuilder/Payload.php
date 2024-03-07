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
      protected readonly ?Payload $signer,
      public readonly string $name,
      public \DateTimeImmutable $expires,
      protected array $signingKeys = [],
    ) {}

    public function addKey(Key $key = null): void
    {
        $key ??= new Key;

        assert(! in_array($key, $this->signingKeys, true), 'A role cannot have the same key twice.');
        $this->signingKeys[] = $key;

        $this->isDirty = true;
        if ($this->signer) {
            $this->signer->isDirty = true;
        }
    }

    public function revokeKey(int $which): void
    {
        array_push($this->revokedKeys, ...array_splice($this->signingKeys, $which, 1));

        $this->isDirty = true;
        if ($this->signer) {
            $this->signer->isDirty = true;
        }
    }

    protected function addPayload(Payload $payload): void
    {
        $this->payloads[$payload->name] = $payload;
    }

    public function __toString(): string
    {
        return self::encodeJson($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function toArray(): array
    {
        $data = $this->getSigned();

        return [
          'signatures' => array_map(fn (Key $key) => $key->sign($data), [
            ...$this->signingKeys,
            ...$this->revokedKeys,
          ]),
          'signed' => $data,
        ];
    }

    public function getSigned(): array
    {
        return [
          'expires' => $this->expires->format('Y-m-d\TH:i:sp'),
          'spec_version' => '1.0.0',
          'version' => $this->version,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

use Tuf\CanonicalJsonTrait;

/**
 * Base class for all roles in a TUF repository.
 *
 * When cast to a string, this will be the canonical JSON representation of
 * the role.
 *
 * @property string $name
 */
abstract class Payload implements \Stringable
{
    use CanonicalJsonTrait;

    public int $threshold = 1;

    public int $version = 0;

    public bool $isDirty = false;

    private array $revokedKeys = [];

    final public const FILE_EXTENSION = 'json';

    public function __construct(
      protected readonly ?Payload $signer,
      public \DateTimeImmutable $expires,
      protected array $signingKeys = [],
    ) {}

    public function addKey(Key $key = null): static
    {
        $key ??= new Key;

        assert(! in_array($key, $this->signingKeys, true), 'A role cannot have the same key twice.');
        $this->signingKeys[] = $key;

        $this->isDirty = true;
        if ($this->signer) {
            $this->signer->isDirty = true;
        }
        return $this;
    }

    public function revokeKey(int $which): void
    {
        array_push($this->revokedKeys, ...array_splice($this->signingKeys, $which, 1));

        $this->isDirty = true;
        if ($this->signer) {
            $this->signer->isDirty = true;
        }
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

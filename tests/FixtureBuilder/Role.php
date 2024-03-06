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
 * @property Key[] $keys
 * @property string $name
 */
abstract class Role implements \Stringable
{
    use CanonicalJsonTrait;

    public int $threshold = 1;

    public int $version = 0;

    public bool $isDirty = false;

    final public const FILE_EXTENSION = 'json';

    public function __construct(
      public \DateTimeImmutable $expires,
      private array $keys = [],
    ) {}

    public function __get(string $name): mixed
    {
        return match ($name) {
            'keys' => $this->keys,
        };
    }

    public function addKey(Key $key = null): static
    {
        $key ??= new Key;

        assert(! in_array($key, $this->keys, true), 'A role cannot have the same key twice.');
        $this->keys[] = $key;

        return $this;
    }

    public function revokeKey(Key|int $which): static
    {
        if ($which instanceof Key) {
            $which = array_search($which, $this->keys, true);
        }
        if (is_int($which)) {
            array_splice($this->keys, $which, 1);
        }

        return $this;
    }

    public function __toString(): string
    {
        return self::encodeJson($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function toArray(): array
    {
        $data = $this->getSigned();

        return [
          'signatures' => array_map(fn (Key $key) => $key->sign($data), $this->keys),
          'signed' => $data,
        ];
    }

    public function getSigned(): array
    {
        return [
          'expires' => $this->expires->format(\DateTimeImmutable::ISO8601),
          'spec_version' => '1.0.0',
          'version' => $this->version,
        ];
    }
}

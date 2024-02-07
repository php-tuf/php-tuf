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
 */
abstract class Role implements \Stringable
{
    use CanonicalJsonTrait;

    public int $threshold = 1;

    public int $version = 1;

    protected ?string $name = null;

    public function __construct(public \DateTimeImmutable $expires, private array $keys = []) {}

    public static function create(\DateTimeImmutable $expiration): static
    {
        return new static($expiration, [new Key]);
    }

    public function __get(string $name): array
    {
        return match ($name) {
            'keys' => $this->keys,
        };
    }

    public function addKey(Key $key): static
    {
        assert(! in_array($key, $this->keys, true));
        $this->keys[] = $key;
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

    public function fileName(bool $withVersion = true): string
    {
        assert(is_string($this->name));

        $name = $this->name . '.json';
        return $withVersion ? $this->version . '.' . $name : $name;
    }
}

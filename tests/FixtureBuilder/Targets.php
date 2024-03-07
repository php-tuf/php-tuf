<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

final class Targets extends Payload
{
    public ?array $paths = null;

    public ?array $pathHashPrefixes = null;

    public bool $terminating = false;

    private array $targets = [];

    public function __construct(
      Root|self $keyRing,
      Snapshot $parent,
      string $name = 'targets',
      mixed ...$arguments,
    ) {
        if ($keyRing instanceof Root) {
            assert($name === 'targets');
        }
        parent::__construct($name, $keyRing, $parent, ...$arguments);
    }

    public function delegate(string $name): self
    {
        return new self($this, $this->parent, $name, $this->expires, [new Key]);
    }

    public function add(string $path, ?string $name = null): void
    {
        assert(is_file($path));

        $name ??= basename($path);
        $this->targets[$name] = $path;

        $this->markAsDirty();
        $this->parent->markAsDirty();

        if ($this->name !== 'targets') {
            $this->parent->payloads['targets']?->markAsDirty();
        }
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        $data['_type'] = 'targets';

        /** @var self $delegation */
        foreach ($this->payloads as $delegation) {
            $role = [
              'name' => $delegation->name,
              'threshold' => $delegation->threshold,
              'terminating' => $delegation->terminating,
            ];
            if (is_array($delegation->paths)) {
                $role['paths'] = $delegation->paths;
            }
            if (is_array($delegation->pathHashPrefixes)) {
                $role['path_hash_prefixes'] = $delegation->pathHashPrefixes;
            }

            foreach ($delegation->signingKeys as $key) {
                $id = $key->id();
                $data['delegations']['keys'][$id] = $key->toArray();
                $role['keyids'][] = $id;
            }
            $data['delegations']['roles'][] = $role;
        }

        foreach ($this->targets as $name => $path) {
            assert(is_file($path));

            $data['targets'][$name] = [
              'hashes' => [
                'sha256' => hash_file('sha256', $path),
                'sha512' => hash_file('sha512', $path),
              ],
              'length' => filesize($path),
            ];
            if ($this->name !== 'targets') {
                $data['targets'][$name]['custom'] = (object) [];
            }
        }

        $data += [
          'delegations' => [
            'keys' => (object) [],
            'roles' => [],
          ],
          'targets' => (object) [],
        ];
        return $data;
    }
}

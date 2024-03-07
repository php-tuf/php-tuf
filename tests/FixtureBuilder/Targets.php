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
      Root|self $signer,
      Snapshot $parent,
      string $name = 'targets',
      mixed ...$arguments,
    ) {
        if ($signer instanceof Root) {
            assert($name === 'targets');
        }
        parent::__construct($signer, $name, ...$arguments);

        $parent->addPayload($this);
    }

    public function add(string $path, ?string $name = null): self
    {
        assert(is_file($path));

        $name ??= basename($path);
        $this->targets[$name] = $path;

        return $this;
    }

    public function addDelegation(self $role): self
    {
        $this->payloads[$role->name] = $role;
        return $this;
    }

    public function getSigned(): array
    {
        $data = parent::getSigned();

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
        $data['_type'] = 'targets';

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

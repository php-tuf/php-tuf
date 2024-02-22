<?php

namespace Tuf\Tests\FixtureBuilder;

class Fixture
{
    public Root $root;

    public Timestamp $timestamp;

    public Snapshot $snapshot;

    /**
     * @var \Tuf\Tests\FixtureBuilder\Targets[]
     */
    public array $targets = [];

    public function __construct(
      public readonly string $baseDir,
      protected ?\DateTimeImmutable $expires = null,
    )
    {
        $this->expires ??= new \DateTimeImmutable('+1 year');

        $this->root = new Root($this->expires, [new Key]);

        $targets = new Targets($this->root, 'targets', $this->expires, [new Key]);
        $this->targets[$targets->name] = $targets;
        $this->root->addRole($targets);

        $this->snapshot = new Snapshot($this->root, $this->expires, [new Key]);
        $this->snapshot->addRole($targets);
        $this->root->addRole($this->snapshot);

        $this->timestamp = new Timestamp($this->root, $this->expires, [new Key]);
        $this->timestamp->setSnapshot($this->snapshot);
        $this->root->addRole($this->timestamp);
    }

    private function all(): array
    {
        return [
          $this->root,
          $this->timestamp,
          $this->snapshot,
          ...$this->targets,
        ];
    }

    private function writeAllToDirectory(string $dir): void
    {
        self::mkDir($dir);

        /** @var \Tuf\Tests\FixtureBuilder\Role $role */
        foreach ($this->all() as $role) {
            $data = (string) $role;
            file_put_contents($dir . '/' . $role->fileName(), $data);
            file_put_contents($dir . '/' . $role->fileName(false), $data);
        }
    }

    public function writeServer(): void
    {
        $this->writeAllToDirectory($this->baseDir . '/server');
    }

    public function writeClient(): void
    {
        $this->writeAllToDirectory($this->baseDir . '/client');
    }

    public function createTarget(string $name, string|Targets|null $signer = 'targets'): void
    {
        $dir = $this->baseDir . '/targets';
        self::mkDir($dir);

        $path = $dir . '/' . $name;
        file_put_contents($path, "Contents: $name");

        if ($signer) {
            if (is_string($signer)) {
                $signer = $this->targets[$signer];
            }
            assert(in_array($signer, $this->targets, true));
            $signer->add($path);
        }
    }

    public function newVersion(): void
    {
        $this->root->isDirty = true;
        $this->timestamp->isDirty = true;
        $this->snapshot->isDirty = true;
        $this->targets['targets']->isDirty = true;

        /** @var Role $role */
        foreach ($this->all() as $role) {
            if ($role->isDirty) {
                $role->version++;
            }
            $role->isDirty = false;
        }
    }

    public function publish(): void
    {
        $this->writeServer();
        $this->writeClient();
        $this->newVersion();
    }

    public function delegate(string|Targets $delegator, string $name, array $properties = []): Targets
    {
        if (is_string($delegator)) {
            $delegator = $this->targets[$delegator];
        }
        assert(in_array($delegator, $this->targets, true));

        $role = new Targets($delegator, $name, $this->expires, [new Key]);
        $this->targets[$name] = $role;
        $delegator->addDelegation($role);

        foreach ($properties as $key => $value) {
            assert(property_exists($role, $key));
            $role->$key = $value;
        }

        $this->snapshot->addRole($role);
        return $role;
    }

    private static function mkDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        assert(mkdir($path, recursive: true));
    }
}


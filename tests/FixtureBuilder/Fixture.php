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

    /**
     * @var \Tuf\Tests\FixtureBuilder\Role[]
     */
    private array $changedRoles = [];

    public function __construct(
      public readonly string $baseDir,
      protected ?\DateTimeImmutable $expires = null,
    )
    {
        $this->expires ??= new \DateTimeImmutable('+1 year');

        $targets = Targets::create($this->expires);
        $this->targets[$targets->name] = $targets;

        $this->snapshot = Snapshot::create($this->expires)
          ->addRole($targets);

        $this->timestamp = Timestamp::create($this->expires)
          ->setSnapshot($this->snapshot);

        $this->root = Root::create($this->expires)
          ->addRole($this->timestamp)
          ->addRole($this->snapshot)
          ->addRole($targets);
    }

    private function markAsChanged(Role $role): void
    {
        if (in_array($role, $this->changedRoles, true)) {
            return;
        }
        $this->changedRoles[] = $role;
    }

    public function write(Role $role, string $dir): void
    {
        self::mkDir($dir);

        $data = (string) $role;
        file_put_contents($dir . '/' . $role->fileName(), $data);
        file_put_contents($dir . '/' . $role->fileName(false), $data);
    }

    private function writeAllToDirectory(string $dir): void
    {
        $roles = [
          $this->root,
          $this->timestamp,
          $this->snapshot,
          ...$this->targets,
        ];
        array_walk($roles, fn (Role $role) => $this->write($role, $dir));
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
            $signer->targets[$name] = $path;
            $this->markAsChanged($signer);
        }
    }

    public function newVersion(): void
    {
        $this->markAsChanged($this->root);
        $this->markAsChanged($this->timestamp);
        $this->markAsChanged($this->snapshot);
        $this->markAsChanged($this->targets['targets']);

        while ($this->changedRoles) {
            array_pop($this->changedRoles)->version++;
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

        $role = new Targets($this->expires, [new Key], $name);
        $this->targets[$name] = $role;
        $delegator->addDelegation($role);
        $this->markAsChanged($delegator);

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


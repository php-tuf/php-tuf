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

    public function __construct(protected string $baseDir, protected ?\DateTimeImmutable $expires = null)
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

    public function createAndSignTarget(string $name, string $signedBy = 'targets'): static
    {
        assert(array_key_exists($signedBy, $this->targets));

        $dir = $this->baseDir . '/targets';
        self::mkDir($dir);

        $path = $this->targets[$signedBy]->targets[$name] = $dir . '/' . $name;
        file_put_contents($path, "Contents: $name");

        return $this;
    }

    public function publish(): void
    {
        $this->writeServer();
        $this->writeClient();

        $this->root->version++;
        $this->timestamp->version++;
        $this->snapshot->version++;
        array_walk($this->targets, fn (Targets $role) => $role->version++);
    }

    private static function mkDir(string $path): void
    {
        if (! is_dir($path)) {
            assert(mkdir($path, recursive: true));
        }
    }
}


<?php

namespace Tuf\Tests\FixtureBuilder;

use Symfony\Component\Filesystem\Filesystem;

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
    private array $dirty = [];

    public function __construct(
      public readonly string $baseDir,
      protected ?\DateTimeImmutable $expires = null,
    )
    {
        $this->expires ??= new \DateTimeImmutable('+1 year');

        $this->root = new Root($this->expires, [new Key]);

        $targets = new Targets('targets', $this->expires, [new Key]);
        $this->targets[$targets->name] = $targets;
        $this->root->addRole($targets);

        $this->snapshot = new Snapshot($this->expires, [new Key]);
        $this->snapshot->withHashes = false;
        $this->snapshot->addRole($targets);
        $this->root->addRole($this->snapshot);

        $this->timestamp = new Timestamp($this->expires, [new Key]);
        $this->timestamp->setSnapshot($this->snapshot);
        $this->root->addRole($this->timestamp);

        $this->invalidate();
    }

    private function markAsDirty(Role $role): void
    {
        if (in_array($role, $this->dirty, true)) {
            return;
        }
        $this->dirty[] = $role;

        if ($role instanceof Targets && $role->name !== 'targets') {
            $this->markAsDirty($this->targets['targets']);
        }
        else {
            $this->markAsDirty($this->root);
        }
    }

    private function writeAllToDirectory(string $dir): void
    {
        self::mkDir($dir);

        foreach ($this->dirty as $role) {
            $data = (string) $role;
            file_put_contents($dir . '/' . $role->fileName(), $data);
            file_put_contents($dir . '/' . $role->fileName(false), $data);
        }
    }

    public function writeClient(): void
    {
        $serverDir = $this->baseDir . '/server';
        $fs = new Filesystem();
        $fs->mirror($serverDir, $this->baseDir . '/client');
    }

    public function createTarget(string $name, string|Targets|null $signer = 'targets'): void
    {
        $dir = $this->baseDir . '/targets';
        self::mkDir($dir);

        $path = $dir . '/' . $name;
        file_put_contents($path, "Contents: $name");

        if ($signer) {
            $this->addTarget($path, $signer);
        }
    }

    public function invalidate(): void
    {
        $this->markAsDirty($this->root);
        $this->markAsDirty($this->timestamp);
        $this->markAsDirty($this->snapshot);
        $this->markAsDirty($this->targets['targets']);
    }

    public function addTarget(string $path, string|Targets $signer = 'targets'): void
    {
        assert(file_exists($path));

        if (is_string($signer)) {
            $signer = $this->targets[$signer];
        }
        assert(in_array($signer, $this->targets, true));
        $signer->add($path);

        $this->markAsDirty($this->snapshot);
        $this->markAsDirty($this->targets['targets']);
        $this->markAsDirty($this->timestamp);
        $this->markAsDirty($signer);
    }

    public function addKey(string $role): void
    {
        $role = $this->targets[$role] ?? $this->$role;
        assert($role instanceof Role);
        $role->addKey(new Key);
        $this->markAsDirty($role);
    }

    public function revokeKey(string $role, int $which = 0): void
    {
        $role = $this->targets[$role] ?? $this->$role;
        assert($role instanceof Role);
        $role->revokeKey($which);
        $this->markAsDirty($role);
    }

    public function publish(bool $withClient = false): void
    {
        $this->markAsDirty($this->root);
        $this->writeAllToDirectory($this->baseDir . '/server');
        if ($withClient) {
            $this->writeClient();
        }
        while ($this->dirty) {
            array_pop($this->dirty)->version++;
        }
    }

    public function delegate(string|Targets $delegator, string $name, array $properties = []): Targets
    {
        if (is_string($delegator)) {
            $delegator = $this->targets[$delegator];
        }
        assert(in_array($delegator, $this->targets, true));

        $role = new Targets($name, $this->expires, [new Key]);
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


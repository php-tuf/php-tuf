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

    public function __construct(
      public readonly string $baseDir,
      protected ?\DateTimeImmutable $expires = null,
    )
    {
        $this->expires ??= new \DateTimeImmutable('+1 year');

        $this->root = new Root($this->expires, [new Key]);
        $this->timestamp = new Timestamp($this->root, $this->expires, [new Key]);

        $this->snapshot = new Snapshot($this->root, $this->timestamp, $this->expires, [new Key]);
        $this->snapshot->withHashes = false;
        $this->snapshot->withLength = false;

        $targets = new Targets($this->root, $this->snapshot, 'targets', $this->expires, [new Key]);
        $this->targets[$targets->name] = $targets;

        $this->invalidate();
    }

    private function writeAllToDirectory(string $dir): void
    {
        self::mkDir($dir);

        $roles = [
          ...array_slice($this->targets, 1),
          $this->root,
          $this->targets['targets'],
          $this->snapshot,
          $this->timestamp,
        ];
        $roles = array_filter($roles, fn (Payload $role) => $role->isDirty);

        foreach ($roles as $role) {
            $role->version++;

            $data = (string) $role;
            $name = $role->name . '.' . $role::FILE_EXTENSION;
            file_put_contents($dir . '/' . $role->version . ".$name", $data);
            file_put_contents($dir . '/' . $name, $data);

            $role->isDirty = false;
        }
    }

    public function writeClient(): void
    {
        $serverDir = $this->baseDir . '/server';
        $fs = new Filesystem();
        $fs->mirror($serverDir, $this->baseDir . '/client', options: [
          'override' => true,
          'delete' => true,
        ]);
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
        $this->root->isDirty = true;
        $this->timestamp->isDirty = true;
        $this->snapshot->isDirty = true;
        $this->targets['targets']->isDirty = true;
    }

    public function addTarget(string $path, string|Targets $signer = 'targets'): void
    {
        assert(file_exists($path));

        if (is_string($signer)) {
            $signer = $this->targets[$signer];
        }
        assert(in_array($signer, $this->targets, true));
        $signer->add($path);
    }

    public function publish(bool $withClient = false): void
    {
        $this->writeAllToDirectory($this->baseDir . '/server');
        if ($withClient) {
            $this->writeClient();
        }
    }

    public function delegate(string|Targets $delegator, string $name, array $properties = []): Targets
    {
        if (is_string($delegator)) {
            $delegator = $this->targets[$delegator];
        }
        assert(in_array($delegator, $this->targets, true));

        $role = $delegator->delegate($name);
        $this->targets[$name] = $role;

        foreach ($properties as $key => $value) {
            assert(property_exists($role, $key));
            $role->$key = $value;
        }

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

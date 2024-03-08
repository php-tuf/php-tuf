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

        $this->root = new Root($this->expires, [
          Key::fromStaticList(),
        ]);
        $this->timestamp = new Timestamp($this->root, $this->expires, [
          Key::fromStaticList(),
        ]);

        $this->snapshot = new Snapshot($this->root, $this->timestamp, $this->expires, [
          Key::fromStaticList(),
        ]);
        $this->snapshot->withHashes = false;
        $this->snapshot->withLength = false;

        $targets = new Targets($this->root, $this->snapshot, 'targets', $this->expires, [
          Key::fromStaticList(),
        ]);
        $this->targets[$targets->name] = $targets;

        $this->invalidate();
    }

    private function writeAllToDirectory(string $dir): void
    {
        self::mkDir($dir);

        if ($this->root->consistentSnapshot) {
            $this->root->markAsDirty();
        }

        $roles = [
          ...$this->targets,
          $this->snapshot,
          $this->timestamp,
          $this->root,
        ];
        foreach ($roles as $role) {
            $clientVersions[] = "$role->name = $role->version";

            $name = $role->name . '.' . $role::FILE_EXTENSION;
            file_put_contents("$dir/$name", (string) $role);
            copy("$dir/$name", "$dir/$role->version.$name");

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

    public function createTarget(string $name, string|Targets|null $signer = 'targets'): string
    {
        $dir = $this->baseDir . '/targets';
        self::mkDir($dir);

        $path = $dir . '/' . $name;
        file_put_contents($path, "Contents: $name");

        if ($signer) {
            $this->addTarget($path, $signer);
        }
        return $path;
    }

    public function invalidate(): void
    {
        $this->root->markAsDirty();
        $this->timestamp->markAsDirty();
        $this->snapshot->markAsDirty();
        $this->targets['targets']->markAsDirty();
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

    public function createHashBins(int $binCount = 1024, array $properties = []): void
    {
        $prefixLength = strlen(sprintf('%x', $binCount - 1));
        $prefixCount = 16 ** $prefixLength;
        $binSize = floor($prefixCount / $binCount);
        assert($prefixCount % $binCount === 0);

        for ($x = 0; $x < $prefixCount; $x += $binSize) {
            $high = $x + $binSize - 1;

            if ($x === $high) {
                $name = sprintf("%0{$prefixLength}x", $x);
            }
            else {
                $name = sprintf("%0{$prefixLength}x-%0{$prefixLength}x", $x, $high);
            }

            if ($binSize === 1) {
                $prefixes = [$name];
            }
            else {
                $prefixes = [];
                for ($y = 0; $y < $x + $binSize; $y++) {
                    $prefixes[] = sprintf("%0{$prefixLength}x", $y);
                }
            }

            $role = $this->delegate('targets', $name, $properties);
            $role->pathHashPrefixes = $prefixes;
        }
    }

    public function addToHashBin(string $path): void
    {
        $search = hash('sha256', str_replace($this->baseDir . '/targets/', '', $path));

        foreach ($this->targets as $role) {
            foreach ($role->pathHashPrefixes ?? [] as $prefix) {
                if (str_starts_with($search, $prefix)) {
                    $role->add($path);
                    return;
                }
            }
        }
        assert(false, "There is no hash bin for $path ($search).");
    }

    private static function mkDir(string $path): void
    {
        $fs = new Filesystem();
        $fs->mkdir($path);
    }
}

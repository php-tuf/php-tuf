<?php

namespace Tuf\Tests\FixtureBuilder;

use Symfony\Component\Filesystem\Filesystem;

/**
 * This is our in-house TUF fixture builder.
 *
 * This class is MEANT FOR TESTING PURPOSES ONLY and should absolutely not be
 * used in production.
 */
class Fixture
{

    public readonly Root $root;

    public readonly Timestamp $timestamp;

    public readonly Snapshot $snapshot;

    /**
     * @var \Tuf\Tests\FixtureBuilder\Targets[]
     */
    public array $targets = [];

    private readonly string $serverDir;

    private readonly string $clientDir;

    private readonly string $targetsDir;

    public function __construct(
        public readonly string $baseDir,
        protected ?\DateTimeImmutable $expires = null,
        private readonly Filesystem $fileSystem = new Filesystem(),
    ) {
        $this->serverDir = $baseDir . '/server';
        $this->clientDir = $baseDir . '/client';
        $this->targetsDir = $baseDir . '/targets';
        $fileSystem->remove([
            $this->serverDir,
            $this->clientDir,
            $this->targetsDir,
        ]);

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

    public function createTarget(
        string $name,
        string|Targets|null $signer = 'targets'
    ): string {
        $this->fileSystem->mkdir($this->targetsDir);

        $path = $this->targetsDir . '/' . $name;
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

    public function addTarget(
        string $path,
        string|Targets $signer = 'targets'
    ): void {
        assert(file_exists($path));

        if (is_string($signer)) {
            $signer = $this->targets[$signer];
        }
        assert(in_array($signer, $this->targets, true));
        $signer->add($path);
    }

    public function publish(bool $withClient = false): void
    {
        $this->fileSystem->mkdir($this->serverDir);

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
            $name = $role->name . '.' . $role::FILE_EXTENSION;
            file_put_contents("$this->serverDir/$name", (string)$role);
            copy("$this->serverDir/$name", "$this->serverDir/$role->version.$name");

            $role->isDirty = false;
        }

        if ($withClient) {
            $this->fileSystem->mirror($this->serverDir, $this->clientDir, options: [
                'override' => true,
                'delete' => true,
            ]);
        }
    }

    public function delegate(
        string|Targets $delegator,
        string $name,
        array $properties = []
    ): Targets {
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
            } else {
                $name = sprintf("%0{$prefixLength}x-%0{$prefixLength}x", $x, $high);
            }

            if ($binSize === 1) {
                $prefixes = [$name];
            } else {
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
        $search = hash('sha256', str_replace("$this->targetsDir/", '', $path));

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
}

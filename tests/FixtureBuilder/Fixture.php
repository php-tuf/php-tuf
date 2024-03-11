<?php

namespace Tuf\Tests\FixtureBuilder;

use Symfony\Component\Filesystem\Filesystem;

/**
 * This is our in-house TUF fixture builder.
 *
 * This class is MEANT FOR TESTING PURPOSES ONLY and should absolutely not be
 * used in production. It is meant to generate complete TUF repositories, but
 * doesn't necessarily support all of the functionality from the Python TUF
 * tool it was derived from.
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

    public readonly string $serverDir;

    private readonly string $clientDir;

    private readonly string $targetsDir;

    public function __construct(
        ?string $baseDir = null,
        ?\DateTimeImmutable $expires = null,
        private readonly Filesystem $fileSystem = new Filesystem(),
    ) {
        // By default, create the fixture in a temporary directory.
        $baseDir ??= uniqid(sys_get_temp_dir() . '/TUF_Fixture_');

        // If there's any previous data in the directory, delete it.
        $this->serverDir = $baseDir . '/server';
        $this->clientDir = $baseDir . '/client';
        $this->targetsDir = $baseDir . '/targets';
        $fileSystem->remove([
            $this->serverDir,
            $this->clientDir,
            $this->targetsDir,
        ]);

        // By default, all metadata we generate should expire in a year. This
        // can be overridden for individual metadata objects.
        $expires ??= new \DateTimeImmutable('+1 year');

        $this->root = new Root($expires, [
            Key::fromStaticList(),
        ]);
        $this->timestamp = new Timestamp($this->root, $expires, [
            Key::fromStaticList(),
        ]);

        $this->snapshot = new Snapshot($this->root, $this->timestamp, $expires, [
            Key::fromStaticList(),
        ]);
        // Disable hashes and file sizes in the snapshot metadata, which matches
        // the default configuration of the Python TUF tool.
        $this->snapshot->withHashes = false;
        $this->snapshot->withLength = false;

        $targets = new Targets($this->root, $this->snapshot, 'targets', $expires, [
            Key::fromStaticList(),
        ]);
        $this->targets[$targets->name] = $targets;

        $this->invalidate();
    }

    /**
     * Creates a target file and signs it.
     *
     * @param string $name
     *   The name of the target file.
     * @param string|\Tuf\Tests\FixtureBuilder\Targets|null $signer
     *   The role which should sign the file, if any. Defaults to the top-level
     *   `targets` role.
     *
     * @return string
     *   The path of the created file.
     */
    public function createTarget(string $name, string|Targets|null $signer = 'targets'): string
    {
        $this->fileSystem->mkdir($this->targetsDir);

        $path = $this->targetsDir . '/' . $name;
        file_put_contents($path, "Contents: $name");

        if ($signer) {
            $this->addTarget($path, $signer);
        }
        return $path;
    }

    /**
     * Marks the four top-level roles (root, snapshot, targets, and timestamp)
     * as having changed.
     *
     * This will ensure their version is bumped next time ::publish() is called.
     */
    public function invalidate(): void
    {
        $this->root->markAsDirty();
        $this->timestamp->markAsDirty();
        $this->snapshot->markAsDirty();
        $this->targets['targets']->markAsDirty();
    }

    /**
     * Signs an already-existing target file.
     *
     * @param string $path
     *   The path of the file to sign.
     * @param string|\Tuf\Tests\FixtureBuilder\Targets $signer
     *   The role which should sign the file, if any. Defaults to the top-level
     *   `targets` role.
     */
    public function addTarget(string $path, string|Targets $signer = 'targets'): void
    {
        assert(file_exists($path));

        if (is_string($signer)) {
            $signer = $this->targets[$signer];
        }
        assert(in_array($signer, $this->targets, true));
        $signer->add($path);
    }

    /**
     * Writes all the metadata which has changed.
     *
     * @param bool $withClient
     *   Whether to also copy the changed metadata to the client-side directory.
     */
    public function publish(bool $withClient = false): void
    {
        $this->fileSystem->mkdir($this->serverDir);

        // If we're doing consistent snapshots, we ALWAYS need to create a new
        // version of the root metadata.
        if ($this->root->consistentSnapshot) {
            $this->root->markAsDirty();
        }

        // The roles need to be written in a specific order so that they are
        // correct relative to each other. (This order was documented in the
        // code of the Python TUF tool.)
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

            // Now that we've written the metadata, we no longer consider it
            // as having "changed".
            $role->isDirty = false;
        }

        // The client directory, if we're creating it, is just a copy of what's
        // on the server. This is also true in the Python TUF tool.
        if ($withClient) {
            $this->fileSystem->mirror($this->serverDir, $this->clientDir, options: [
                'override' => true,
                'delete' => true,
            ]);
        }
    }

    /**
     * Creates a delegated targets role.
     *
     * @param string|\Tuf\Tests\FixtureBuilder\Targets $delegator
     *   The role that is delegating trust, or its name.
     * @param string $name
     *   The name of the delegated role.
     * @param mixed[] $properties
     *   Any properties which should be set on the delegated role, keyed by
     *   property name. (For example, ['terminating' => true].)
     *
     * @return \Tuf\Tests\FixtureBuilder\Targets
     *   The delegated role.
     */
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

    /**
     * Creates a set of hash bin delegated roles.
     *
     * @param int $binCount
     *   The number of bins to create.
     * @param mixed[] $properties
     *   Any properties which should be set on the delegated roles, keyed by
     *   property name. (For example, ['terminating' => true].)
     */
    public function createHashBins(int $binCount = 1024, array $properties = []): void
    {
        // All of this code is adapted directly, and basically without changes,
        // from the Python TUF tool.
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
                $properties['pathHashPrefixes'] = [$name];
            } else {
                for ($y = 0; $y < $x + $binSize; $y++) {
                    $properties['pathHashPrefixes'][] = sprintf("%0{$prefixLength}x", $y);
                }
            }
            $this->delegate('targets', $name, $properties);
        }
    }

    /**
     * Adds a target file to the appropriate hash bin.
     *
     * This expects that ::createHashBins() has previously been called.
     *
     * @param string $path
     *   The path of the target file to add.
     */
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

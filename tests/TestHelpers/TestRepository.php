<?php

namespace Tuf\Tests\TestHelpers;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\RepositoryInterface;

class TestRepository implements RepositoryInterface
{
    private array $metadata = [];

    public function __construct(private RepositoryInterface $decorated)
    {
    }

    public function getRoot(int $version): PromiseInterface
    {
        return $this->metadata["$version.root"] ?? $this->decorated->getRoot($version);
    }

    public function setRoot(?RootMetadata $metadata): void
    {
        $version = $metadata->getVersion();
        if ($metadata) {
            $this->metadata["$version.root"] = Create::promiseFor($metadata);
        } else {
            unset($this->metadata["$version.root"]);
        }
    }

    public function getTimestamp(): PromiseInterface
    {
        return $this->metadata['timestamp'] ?? $this->decorated->getTimestamp();
    }

    public function setTimestamp(?TimestampMetadata $metadata): void
    {
        if ($metadata) {
            $this->metadata['timestamp'] = Create::promiseFor($metadata);
        } else {
            unset($this->metadata['timestamp']);
        }
    }

    public function getSnapshot(?int $version, ...$arguments): PromiseInterface
    {
        $key = var_export($version, true) . '.snapshot';
        return $this->metadata[$key] ?? $this->decorated->getSnapshot($version, ...$arguments);
    }

    public function setSnapshot(?int $version, ?SnapshotMetadata $metadata): void
    {
        $version = var_export($version, true);
        if ($metadata) {
            $this->metadata["$version.snapshot"] = Create::promiseFor($metadata);
        } else {
            unset($this->metadata["$version.snapshot"]);
        }
    }

    public function getTargets(?int $version, string $role = 'targets', ...$arguments): PromiseInterface
    {
        $key = var_export($version, true) . '.role';
        return $this->metadata[$key] ?? $this->decorated->getTargets($version, $role, ...$arguments);
    }

    public function setTargets(?int $version, string $role, ?TargetsMetadata $metadata): void
    {
        $version = var_export($version, true);
        if ($metadata) {
            $this->metadata["$version.$role"] = Create::promiseFor($metadata);
        } else {
            unset($this->metadata["$version.$role"]);
        }
    }
}

<?php

namespace Tuf\Tests\TestHelpers;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\RepositoryInterface;

class TestRepository implements RepositoryInterface
{
    private array $promises = [];

    public function __construct(private string $baseDir)
    {
    }

    public function set(string $fileName, $data): void
    {
        if ($data === NULL) {
            unset($this->promises[$fileName]);
        } elseif ($data instanceof MetadataBase || is_string($data)) {
            $this->set($fileName, new FulfilledPromise($data));
        } elseif ($data === 404) {
            $error = new RepoFileNotFound("$fileName not found");
            $this->set($fileName, new RejectedPromise($error));
        } elseif ($data instanceof PromiseInterface) {
            $this->promises[$fileName] = $data;
        } else {
            throw new \InvalidArgumentException("Only strings, promises, or MetadataBase objects can be set in a test repository.");
        }
    }

    private function load(string $fileName): PromiseInterface
    {
        if (array_key_exists($fileName, $this->promises)) {
            return $this->promises[$fileName];
        }

        $filePath = $this->baseDir . '/' . $fileName;
        if (file_exists($filePath)) {
            $data = file_get_contents($filePath);
            return new FulfilledPromise($data);
        } else {
            $error = new RepoFileNotFound("$fileName not found");
            return new RejectedPromise($error);
        }
    }

    public function getRoot(int $version): PromiseInterface
    {
        $onSuccess = function ($data): RootMetadata {
            return $data instanceof RootMetadata ? $data : RootMetadata::createFromJson($data);
        };
        $onFailure = function (\Throwable $e) {
            return $e instanceof RepoFileNotFound ? new FulfilledPromise(null) : throw $e;
        };
        return $this->load("$version.root.json")->then($onSuccess, $onFailure);
    }

    public function getTimestamp(): PromiseInterface
    {
        return $this->load("timestamp.json")
            ->then(function ($data): TimestampMetadata {
                return $data instanceof TimestampMetadata ? $data : TimestampMetadata::createFromJson($data);
            });
    }

    public function getSnapshot(?int $version): PromiseInterface
    {
        $fileName = 'snapshot.json';
        if (isset($version)) {
            $fileName = "$version.$fileName";
        }
        return $this->load($fileName)
            ->then(function ($data): SnapshotMetadata {
                return $data instanceof SnapshotMetadata ? $data : SnapshotMetadata::createFromJson($data);
            });
    }

    public function getTargets(?int $version, string $role = 'targets'): PromiseInterface
    {
        $fileName = "$role.json";
        if (isset($version)) {
            $fileName = "$version.$fileName";
        }
        return $this->load($fileName)
            ->then(function ($data) use ($role): TargetsMetadata {
                return $data instanceof TargetsMetadata ? $data : TargetsMetadata::createFromJson($data, $role);
            });
    }
}

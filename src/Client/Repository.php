<?php

namespace Tuf\Client;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\LoaderInterface;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;

class Repository
{
    public function __construct(private LoaderInterface $loader)
    {
    }

    public function getRoot(int $version): PromiseInterface
    {
        $onSuccess = function (StreamInterface $data): RootMetadata {
            return RootMetadata::createFromJson($data->getContents());
        };
        // If the next version of the root metadata doesn't exist, it's not
        // an error -- it just means there's nothing newer. So we can safely
        // fulfill the promise with null.
        $onFailure = function (\Throwable $e) {
            if ($e instanceof RepoFileNotFound) {
                return null;
            } else {
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        };
        return $this->loader->load("$version.root.json", Updater::MAXIMUM_DOWNLOAD_BYTES)
            ->then($onSuccess, $onFailure);
    }

    public function getTimestamp(): PromiseInterface
    {
        return $this->loader->load('timestamp.json', Updater::MAXIMUM_DOWNLOAD_BYTES)
            ->then(function (StreamInterface $data): TimestampMetadata {
                return TimestampMetadata::createFromJson($data->getContents());
            });
    }

    public function getSnapshot(?int $version, int $maxBytes = Updater::MAXIMUM_DOWNLOAD_BYTES): PromiseInterface
    {
        $name = isset($version) ? "$version.snapshot" : 'snapshot';

        return $this->loader->load("$name.json", $maxBytes)
            ->then(function (StreamInterface $data): SnapshotMetadata {
                return SnapshotMetadata::createFromJson($data->getContents());
            });
    }

    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = Updater::MAXIMUM_DOWNLOAD_BYTES): PromiseInterface
    {
        $name = isset($version) ? "$version.$role" : $role;

        return $this->loader->load("$name.json", $maxBytes)
            ->then(function (StreamInterface $data) use ($role): TargetsMetadata {
                return TargetsMetadata::createFromJson($data->getContents(), $role);
            });
    }
}

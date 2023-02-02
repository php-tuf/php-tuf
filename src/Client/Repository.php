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

/**
 * Defines a backend to load untrusted TUF metadata objects.
 */
class Repository
{
    /**
     * The maximum number of bytes to download if the remote file size is not
     * known.
     *
     * @var int
     */
    public const MAX_BYTES = 100000;

    public function __construct(private LoaderInterface $loader)
    {
    }

    /**
     * Loads untrusted root metadata.
     *
     * @param int $version
     *   The version of the root metadata to load.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<?\Tuf\Metadata\RootMetadata>
     *   A promise wrapping either an instance of \Tuf\Metadata\RootMetadata,
     *   or null if the requested version of the metadata doesn't exist.
     */
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
        return $this->loader->load("$version.root.json", self::MAX_BYTES)
            ->then($onSuccess, $onFailure);
    }

    public function getTimestamp(): PromiseInterface
    {
        return $this->loader->load('timestamp.json', self::MAX_BYTES)
            ->then(function (StreamInterface $data): TimestampMetadata {
                return TimestampMetadata::createFromJson($data->getContents());
            });
    }

    public function getSnapshot(?int $version, int $maxBytes = self::MAX_BYTES): PromiseInterface
    {
        $name = isset($version) ? "$version.snapshot" : 'snapshot';

        return $this->loader->load("$name.json", $maxBytes)
            ->then(function (StreamInterface $data): SnapshotMetadata {
                return SnapshotMetadata::createFromJson($data->getContents());
            });
    }

    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = self::MAX_BYTES): PromiseInterface
    {
        $name = isset($version) ? "$version.$role" : $role;

        return $this->loader->load("$name.json", $maxBytes)
            ->then(function (StreamInterface $data) use ($role): TargetsMetadata {
                return TargetsMetadata::createFromJson($data->getContents(), $role);
            });
    }
}

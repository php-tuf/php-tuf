<?php

namespace Tuf;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Downloader\DownloaderInterface;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;

class GuzzleRepository implements RepositoryInterface
{
    public const MAXIMUM_BYTES = 10 * 1024;

    public function __construct(private DownloaderInterface $downloader)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot(int $version): PromiseInterface
    {
        $onSuccess = function (StreamInterface $data): RootMetadata {
            return RootMetadata::createFromJson($data->getContents());
        };
        $onFailure = function (\Throwable $e) {
            if ($e instanceof RepoFileNotFound) {
                // If the file wasn't found, it's not an error condition; it
                // just means there is no newer root metadata available. So
                // fulfill the promise with null.
                return new FulfilledPromise(null);
            } elseif ($e instanceof DownloadSizeException) {
                // We don't really need to preserve the backtrace for a
                // DownloadSizeException, since its cause is pretty clear.
                throw $e;
            } else {
                // In all other cases, wrap the exception to keep the backtrace.
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        };

        return $this->doFetch($version, 'root.json', self::MAXIMUM_BYTES)
            ->then($onSuccess, $onFailure);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(): PromiseInterface
    {
        return $this->doFetch(null, 'timestamp.json', self::MAXIMUM_BYTES)
            ->then(function (StreamInterface $data): TimestampMetadata {
                return TimestampMetadata::createFromJson($data->getContents());
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getSnapshot(?int $version, int $maxBytes = self::MAXIMUM_BYTES): PromiseInterface
    {
        return $this->doFetch($version, 'snapshot.json', $maxBytes)
            ->then(function (StreamInterface $data): SnapshotMetadata {
                return SnapshotMetadata::createFromJson($data->getContents());
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = self::MAXIMUM_BYTES): PromiseInterface
    {
        return $this->doFetch($version, "$role.json", $maxBytes)
            ->then(function (StreamInterface $data) use ($role): TargetsMetadata {
                return TargetsMetadata::createFromJson($data->getContents(), $role);
            });
    }

    protected function doFetch(?int $version, string $fileName, int $maxBytes): PromiseInterface
    {
        if (isset($version)) {
            $fileName = "$version.$fileName";
        }
        return $this->downloader->download($fileName, $maxBytes);
    }
}

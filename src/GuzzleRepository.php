<?php

namespace Tuf;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;

class GuzzleRepository implements RepositoryInterface
{
    public const MAXIMUM_BYTES = 10 * 1024;

    public function __construct(private ClientInterface $client)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot(int $version, array $options = []): PromiseInterface
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

        return $this->doFetch($version, 'root.json', self::MAXIMUM_BYTES, $options)
            ->then($onSuccess, $onFailure);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(array $options = []): PromiseInterface
    {
        return $this->doFetch(null, 'timestamp.json', self::MAXIMUM_BYTES, $options)
            ->then(function (StreamInterface $data): TimestampMetadata {
                return TimestampMetadata::createFromJson($data->getContents());
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getSnapshot(?int $version, int $maxBytes = self::MAXIMUM_BYTES, array $options = []): PromiseInterface
    {
        return $this->doFetch($version, 'snapshot.json', $maxBytes, $options)
            ->then(function (StreamInterface $data): SnapshotMetadata {
                return SnapshotMetadata::createFromJson($data->getContents());
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = self::MAXIMUM_BYTES, array $options = []): PromiseInterface
    {
        return $this->doFetch($version, "$role.json", $maxBytes, $options)
            ->then(function (StreamInterface $data) use ($role): TargetsMetadata {
                return TargetsMetadata::createFromJson($data->getContents(), $role);
            });
    }

    protected function doFetch(?int $version, string $fileName, int $maxBytes, array $options): PromiseInterface
    {
        if (isset($version)) {
            $fileName = "$version.$fileName";
        }
        $error = new DownloadSizeException("$fileName exceeded $maxBytes bytes");

        // Periodically check the number of bytes that have been downloaded and
        // throw an exception if it exceeds $maxBytes. This only works with
        // cURL, so we also check the download size in $onSuccess.
        $onProgress = function (int $expectedBytes, int $downloadedBytes) use ($maxBytes, $error) {
            if ($expectedBytes > $maxBytes || $downloadedBytes > $maxBytes) {
                throw $error;
            }
        };
        $options += [
            RequestOptions::PROGRESS => $onProgress,
            RequestOptions::STREAM => true,
        ];

        $onSuccess = function (ResponseInterface $response) use ($maxBytes, $error): StreamInterface {
            $body = $response->getBody();

            $size = $body->getSize();
            if (isset($size)) {
                if ($size > $maxBytes) {
                    throw $error;
                }
            } else {
                // @todo Handle non-seekable streams.
                // https://github.com/php-tuf/php-tuf/issues/169
                $body->rewind();
                $body->read($maxBytes);

                // If we're still not at the end of the stream after reading
                // $maxBytes, it's too long.
                if ($body->eof() === false) {
                    throw $error;
                }
                $body->rewind();
            }
            return $body;
        };
        $onFailure = function (\Throwable $e) use ($fileName) {
            if ($e instanceof ClientException) {
                if ($e->getCode() === 404) {
                    throw new RepoFileNotFound("$fileName not found", 0, $e);
                } else {
                    // Re-throwing the original exception will blow away the
                    // backtrace, so wrap the exception in a more generic one to aid
                    // in debugging.
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            }
            throw $e;
        };

        return $this->client->requestAsync('GET', $fileName, $options)
            ->then($onSuccess, $onFailure);
    }
}

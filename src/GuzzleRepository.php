<?php

namespace Tuf;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
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
        $onFinish = function (string $data): RootMetadata {
            return RootMetadata::createFromJson($data);
        };
        $onFailure = function (\Throwable $e) {
            // If the file wasn't found, it's not an error condition; it just
            // means there is no newer root metadata available. So fulfill the
            // promise with null.
            if ($e instanceof RepoFileNotFound) {
                return new FulfilledPromise(null);
            } else {
                // Wrap the exception to preserve the original backtrace.
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        };

        return $this->doFetch($version, 'root.json', self::MAXIMUM_BYTES, $options)
            ->then($onFinish, $onFailure);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(array $options = []): PromiseInterface
    {
        return $this->doFetch(null, 'timestamp.json', self::MAXIMUM_BYTES, $options)
            ->then(function (string $data): TimestampMetadata {
                return TimestampMetadata::createFromJson($data);
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getSnapshot(?int $version, int $maxBytes = self::MAXIMUM_BYTES, array $options = []): PromiseInterface
    {
        return $this->doFetch($version, 'snapshot.json', $maxBytes, $options)
            ->then(function (string $data): SnapshotMetadata {
                return SnapshotMetadata::createFromJson($data);
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets(?int $version, string $role = 'targets', int $maxBytes = self::MAXIMUM_BYTES, array $options = []): PromiseInterface
    {
        return $this->doFetch($version, "$role.json", $maxBytes, $options)
            ->then(function (string $data): TargetsMetadata {
                return TargetsMetadata::createFromJson($data);
            });
    }

    protected function doFetch(?int $version, string $fileName, int $maxBytes, array $options): PromiseInterface
    {
        if (isset($version)) {
            $fileName = "$version.$fileName";
        }

        $checkSize = function (int $expectedBytes, int $downloadedBytes) use ($fileName, $maxBytes) {
            if ($expectedBytes > $maxBytes || $downloadedBytes > $maxBytes) {
                throw new DownloadSizeException("$fileName exceeded $maxBytes bytes");
            }
        };
        // Periodically check the number of bytes that have been downloaded and
        // throw an exception if it exceeds $maxBytes. Note that this only works
        // with cURL, so we also check the download size in $onFinish.
        $options += [
            RequestOptions::PROGRESS => $checkSize,
        ];

        $onFinish = function (ResponseInterface $response) use ($checkSize): string {
            $body = $response->getBody();
            $data = $body->getContents();

            // Ensure the downloaded data didn't exceed $maxBytes.
            $expectedBytes = $response->getHeaderLine('Content-Length') ?: 0;
            $downloadedBytes = $body->getSize() ?? mb_strlen($data);
            $checkSize($expectedBytes, $downloadedBytes);

            return $data;
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
            ->then($onFinish, $onFailure);
    }
}

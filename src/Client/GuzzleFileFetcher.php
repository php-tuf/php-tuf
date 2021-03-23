<?php

namespace Tuf\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\RepoFileNotFound;

/**
 * Defines a file fetcher that uses Guzzle to read a file over HTTPS.
 */
class GuzzleFileFetcher implements RepoFileFetcherInterface
{
    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * The path prefix for metadata.
     *
     * @var string|null
     */
    private $metaDataPrefix;

    /**
     * The path prefix for targets.
     *
     * @var string|null
     */
    private $targetsPrefix;

    /**
     * GuzzleFileFetcher constructor.
     *
     * @param \GuzzleHttp\ClientInterface $client
     *   The HTTP client.
     * @param string $metaDataPrefix
     *   The path prefix for metadata.
     * @param string $targetsPrefix
     *   The path prefix for targets.
     */
    public function __construct(ClientInterface $client, string $metaDataPrefix, string $targetsPrefix)
    {
        $this->client = $client;
        $this->metaDataPrefix = $metaDataPrefix;
        $this->targetsPrefix = $targetsPrefix;
    }

    /**
     * Creates an instance of this class with a specific base URI.
     *
     * @param string $baseUri
     *   The base URI from which to fetch files.
     * @param string $metaDataPrefix
     *   (optional) The path prefix for metadata. Defaults to '/metadata/'.
     * @param string $targetsPrefix
     *   (optional) The path prefix for targets. Defaults to '/targets/'.
     *
     * @return static
     *   A new instance of this class.
     */
    public static function createFromUri(string $baseUri, string $metaDataPrefix = '/metadata/', string $targetsPrefix = '/targets/') : self
    {
        $client = new Client(['base_uri' => $baseUri]);
        return new static($client, $metaDataPrefix, $targetsPrefix);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchMetaData(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->fetchFile($this->metaDataPrefix . $fileName, $maxBytes);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchTarget(string $fileName, int $maxBytes, array $options = [], string $url = null): PromiseInterface
    {
        // Allow calling code to download the target from an arbitrary URL.
        $fileName = $url ?? $fileName;
        // If $fileName isn't a full URL, treat it as a relative path and prefix
        // it with $this->targetsPrefix.
        // @todo Revisit the need for this bypass once
        // https://github.com/php-tuf/php-tuf/issues/128 is resolved. This was
        // originally added because of the workaround described there.
        if (parse_url($fileName, PHP_URL_HOST) === null) {
            $fileName = $this->targetsPrefix . $fileName;
        }
        return $this->fetchFile($fileName, $maxBytes, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchFile(string $fileName, int $maxBytes, array $options = []): PromiseInterface
    {
        // Create a progress callback to abort the download if it exceeds
        // $maxBytes. This will only work with cURL, so we also verify the
        // download size when request is finished.
        $progress = function (int $expectedBytes, int $downloadedBytes) use ($fileName, $maxBytes) {
            if ($expectedBytes > $maxBytes || $downloadedBytes > $maxBytes) {
                throw new DownloadSizeException("$fileName exceeded $maxBytes bytes");
            }
        };
        $options += [RequestOptions::PROGRESS => $progress];

        return $this->client->requestAsync('GET', $fileName, $options)
            ->then(
                $this->onFulfilled($fileName, $maxBytes),
                $this->onRejected($fileName)
            );
    }

    /**
     * Creates a callback function for when the promise is fulfilled.
     *
     * @param string $fileName
     *   The file name being fetched from the remote repo.
     * @param integer $maxBytes
     *   The maximum number of bytes to download.
     *
     * @return \Closure
     *   The callback function.
     */
    private function onFulfilled(string $fileName, int $maxBytes): \Closure
    {
        return function (ResponseInterface $response) use ($fileName, $maxBytes) {
            $body = $response->getBody();
            $size = $body->getSize();

            if (isset($size)) {
                if ($size > $maxBytes) {
                    throw new DownloadSizeException("$fileName exceeded $maxBytes bytes");
                }
            } else {
                $body->read($maxBytes);

                // If we reached the end of the stream, we didn't exceed the
                // maximum number of bytes.
                if ($body->eof() === false) {
                    throw new DownloadSizeException("$fileName exceeded $maxBytes bytes");
                }
                $body->rewind();
            }
            return new ResponseStream($response);
        };
    }

    /**
     * Creates a callback function for when the promise is rejected.
     *
     * @param string $fileName
     *   The file name being fetched from the remote repo.
     *
     * @return \Closure
     *   The callback function.
     */
    private function onRejected(string $fileName): \Closure
    {
        return function (\Throwable $e) use ($fileName) {
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
    }

    /**
     * {@inheritDoc}
     */
    public function fetchMetaDataIfExists(string $fileName, int $maxBytes): ?string
    {
        try {
            return $this->fetchMetaData($fileName, $maxBytes)->wait();
        } catch (RepoFileNotFound $exception) {
            return null;
        }
    }
}

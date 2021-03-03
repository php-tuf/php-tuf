<?php

namespace Tuf\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\PromiseInterface;
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
     * GuzzleFileFetcher constructor.
     *
     * @param \GuzzleHttp\ClientInterface $client
     *   The HTTP client.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Creates an instance of this class with a specific base URI.
     *
     * @param string $baseUri
     *   The base URI from which to fetch files.
     *
     * @return static
     *   A new instance of this class.
     */
    public static function createFromUri(string $baseUri) : self
    {
        $scheme = parse_url($baseUri, PHP_URL_SCHEME);
        if ($scheme === 'https') {
            $client = new Client(['base_uri' => $baseUri]);
            return new static($client);
        } else {
            throw new \InvalidArgumentException("Repo base URI must be HTTPS: $baseUri");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function fetchMetaData(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->fetchFile($fileName, $maxBytes);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchTarget(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->fetchFile($fileName, $maxBytes);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchFile(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->client->requestAsync('GET', $fileName)
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
            $body->read($maxBytes);

            // If we reached the end of the stream, we didn't exceed the maximum
            // number of bytes.
            if ($body->eof() === true) {
                $body->rewind();
                return $body;
            }
            throw new DownloadSizeException("$fileName exceeded $maxBytes bytes");
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

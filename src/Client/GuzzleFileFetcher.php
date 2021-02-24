<?php

namespace Tuf\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
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
     * {@inheritdoc}
     */
    public function fetchFile(string $fileName, int $maxBytes): PromiseInterface
    {
        try {
            $response = $this->client->request('GET', $fileName);
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                throw new RepoFileNotFound("$fileName not found", 0, $e);
            } else {
                // Re-throwing the original exception will blow away the
                // backtrace, so wrap the exception in a more generic one to aid
                // in debugging.
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $body = $response->getBody();
        $contents = $body->read($maxBytes);
        // If we reached the end of the stream, we didn't exceed the maximum
        // number of bytes.
        if ($body->eof() === true) {
            return new FulfilledPromise($contents);
        } else {
            throw new DownloadSizeException("$fileName exceeded $maxBytes bytes");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFileIfExists(string $fileName, int $maxBytes):?string
    {
        try {
            return $this->fetchFile($fileName, $maxBytes)->wait();
        } catch (RepoFileNotFound $exception) {
            return null;
        }
    }
}

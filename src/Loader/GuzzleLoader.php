<?php

namespace Tuf\Loader;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\RepoFileNotFound;

/**
 * Defines a data loader that initiates a download using Guzzle.
 */
class GuzzleLoader implements LoaderInterface
{
    public function __construct(private ClientInterface $client)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes): StreamInterface
    {
        // Always try to stream the file from the server.
        $options = [RequestOptions::STREAM => true];

        // If using cURL, periodically check how many bytes have been
        // downloaded and throw an exception if it exceeds $maxBytes.
        $onProgress = function (int $expectedBytes, int $downloadedBytes) use ($uri, $maxBytes) {
            if ($expectedBytes > $maxBytes || $downloadedBytes > $maxBytes) {
                throw new DownloadSizeException("$uri exceeded $maxBytes bytes");
            }
        };
        $options[RequestOptions::PROGRESS] = $onProgress;

        try {
            return $this->client->request('GET', $uri, $options)->getBody();
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                throw new RepoFileNotFound("$uri not found", $e->getCode(), $e);
            } else {
                // Wrap the original exception to preserve the backtrace.
                throw new ClientException($e->getMessage(), $e->getRequest(), $e->getResponse(), $e, $e->getHandlerContext());
            }
        }
    }
}

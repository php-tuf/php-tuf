<?php

namespace Tuf\Downloader;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;

class GuzzleDownloader implements DownloaderInterface
{
    public function __construct(private ClientInterface $client)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function download(string $uri, int $maxBytes = null): PromiseInterface
    {
        $options = [RequestOptions::STREAM => true];

        if (isset($maxBytes)) {
            // If using cURL, periodically check how many bytes that have been
            // downloaded and throw an exception if it exceeds $maxBytes.
            $onProgress = function (int $expectedBytes, int $downloadedBytes) use ($uri, $maxBytes) {
                if ($expectedBytes > $maxBytes || $downloadedBytes > $maxBytes) {
                    throw new DownloadSizeException("$uri exceeded $maxBytes bytes.");
                }
            };
            $options[RequestOptions::PROGRESS] = $onProgress;
        }
        return $this->client->requestAsync('GET', $uri, $options)
            ->then(fn (ResponseInterface $response): StreamInterface => $response->getBody());
    }
}

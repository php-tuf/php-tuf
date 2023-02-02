<?php

namespace Tuf\Downloader;

use GuzzleHttp\Promise\PromiseInterface;

interface DownloaderInterface
{
    /**
     * Starts downloading a file.
     *
     * @param string $uri
     *   The relative or absolute URI of the file to download.
     * @param int|null $maxBytes
     *   The maximum number of bytes to download, or null to ignore.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<\Psr\Http\Message\StreamInterface>
     *   A promise wrapping around a data stream of the file. If the file cannot
     *   be found, the promise should be rejected with an instance of
     *   \Tuf\Exception\RepoFileNotFound.
     */
    public function download(string $uri, int $maxBytes = null): PromiseInterface;
}

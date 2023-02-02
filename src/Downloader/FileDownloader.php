<?php

namespace Tuf\Downloader;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\Filesystem\Path;

class FileDownloader implements DownloaderInterface
{
    public function __construct(private string $baseDir)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function download(string $path, int $maxBytes = null): PromiseInterface
    {
        if (Path::isRelative($path)) {
            $path = $this->baseDir . DIRECTORY_SEPARATOR . $path;
        }

        try {
            $data = Utils::tryFopen($path, 'r');
            $data = Utils::streamFor($data);

            return Create::promiseFor($data);
        } catch (\Throwable $error) {
            return Create::rejectionFor($error);
        }
    }
}

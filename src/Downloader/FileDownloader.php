<?php

namespace Tuf\Downloader;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Tuf\Exception\RepoFileNotFound;

/**
 * Defines a file downloader that loads from the local file system.
 */
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
        $path = $this->baseDir . DIRECTORY_SEPARATOR . $path;

        if (file_exists($path)) {
            try {
                $data = Utils::tryFopen($path, 'r');
                $data = Utils::streamFor($data);

                return Create::promiseFor($data);
            } catch (\Throwable $error) {
                return Create::rejectionFor($error);
            }
        } else {
            $error = new RepoFileNotFound("$path not found.");
            return Create::rejectionFor($error);
        }
    }
}

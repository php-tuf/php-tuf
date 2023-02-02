<?php

namespace Tuf\Tests\TestHelpers;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\Downloader\DownloaderInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Metadata\MetadataBase;

class ArrayDownloader implements DownloaderInterface
{
    public array $files = [];

    public array $fileSizes = [];

    public function __construct(private DownloaderInterface $decorated)
    {
    }

    public function set(string $fileName, $data): void
    {
        if ($data === null) {
            unset($this->files[$fileName]);
        } elseif (is_string($data)) {
            $this->set($fileName, Utils::streamFor($data));
        } elseif ($data instanceof MetadataBase || $data instanceof StreamInterface) {
            $this->set($fileName, new FulfilledPromise($data));
        } elseif ($data === 404) {
            $error = new RepoFileNotFound("$fileName not found");
            $this->set($fileName, new RejectedPromise($error));
        } elseif ($data instanceof PromiseInterface) {
            $this->files[$fileName] = $data;
        } else {
            throw new \InvalidArgumentException("Only strings, promises, or MetadataBase objects can be set in a test repository.");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function download(string $uri, int $maxBytes = null): PromiseInterface
    {
        $this->fileSizes[$uri] = $maxBytes;

        if (array_key_exists($uri, $this->files)) {
            return $this->files[$uri];
        } else {
            return $this->decorated->download($uri, $maxBytes);
        }
    }
}
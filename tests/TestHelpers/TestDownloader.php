<?php

namespace Tuf\Tests\TestHelpers;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\Downloader\DownloaderInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Metadata\MetadataBase;

class TestDownloader implements DownloaderInterface
{
    public array $files = [];

    public array $fileSizes = [];

    public function __construct(private ?DownloaderInterface $decorated = null)
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
            $this->files[$fileName] = new RepoFileNotFound("$fileName not found.");
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
            $value = $this->files[$uri];

            if ($value instanceof \Throwable) {
                return Create::rejectionFor($value);
            } else {
                return Create::promiseFor($value);
            }
        } elseif ($this->decorated) {
            return $this->decorated->download($uri, $maxBytes);
        }
        throw new \LogicException("No value was set for $uri and there is nothing being decorated.");
    }
}

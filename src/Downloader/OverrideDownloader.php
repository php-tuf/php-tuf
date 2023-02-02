<?php

namespace Tuf\Downloader;

use GuzzleHttp\Promise\PromiseInterface;

class OverrideDownloader implements DownloaderInterface
{
    public array $overrides = [];

    public function __construct(private DownloaderInterface $decorated)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function download(string $uri, int $maxBytes = null): PromiseInterface
    {
        if (array_key_exists($uri, $this->overrides)) {
            return $this->overrides[$uri];
        } else {
            return $this->decorated->download($uri, $maxBytes);
        }
    }
}

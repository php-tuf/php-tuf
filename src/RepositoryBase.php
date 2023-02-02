<?php

namespace Tuf;

use GuzzleHttp\Promise\PromiseInterface;
use Tuf\Downloader\DownloaderInterface;

abstract class RepositoryBase implements RepositoryInterface
{
    public function __construct(private DownloaderInterface $downloader)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getRoot(int $version): PromiseInterface
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSnapshot(?int $version): PromiseInterface
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets(?int $version, string $role = 'targets'): PromiseInterface
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp(): PromiseInterface
    {
    }
}

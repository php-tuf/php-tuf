<?php

namespace Tuf\Loader;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Tuf\Exception\RepoFileNotFound;

/**
 * Defines a data loader that reads from the local file system.
 */
class FileLoader implements LoaderInterface
{
    public function __construct(private string $baseDir = '')
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): PromiseInterface
    {
        $path = $this->baseDir . DIRECTORY_SEPARATOR . $uri;

        if (! file_exists($path)) {
            $error = new RepoFileNotFound("$uri not found");
            return Create::rejectionFor($error);
        }

        try {
            $resource = Utils::tryFopen($path, 'r');
            $stream = Utils::streamFor($resource);

            return Create::promiseFor($stream);
        } catch (\Throwable $e) {
            return Create::rejectionFor($e);
        }
    }
}

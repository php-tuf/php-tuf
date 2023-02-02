<?php

namespace Tuf\Tests\TestHelpers;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\LoaderInterface;

class FileLoader implements LoaderInterface
{
    public function __construct(private string $baseDir)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): StreamInterface
    {
        $path = $this->baseDir . DIRECTORY_SEPARATOR . $uri;

        if (file_exists($path)) {
            $resource = Utils::tryFopen($path, 'r');
            return Utils::streamFor($resource);
        } else {
            throw new RepoFileNotFound("$uri not found");
        }
    }
}

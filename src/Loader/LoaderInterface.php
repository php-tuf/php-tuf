<?php

namespace Tuf\Loader;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Defines an interface to load data as a stream.
 *
 * The data source can be anything, from anywhere, but it must be returned as an
 * implementation of \Psr\Http\Message\StreamInterface, wrapped by an
 * implementation of \GuzzleHttp\Promise\PromiseInterface.
 */
interface LoaderInterface
{
    /**
     * Loads data as a stream.
     *
     * @param string $uri
     *   The URI of the data to load. The meaning of this depends on the
     *   implementing class; it could be a URL, a relative or absolute file
     *   path, or something else.
     * @param int|null $maxBytes
     *   (optional) The maximum number of bytes that should be read from the
     *   data source, or null to have no limit.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<\Psr\Http\Message\StreamInterface>
     *   A promise, fulfilled by an implementation of
     *   \Psr\Http\Message\StreamInterface. If the data cannot be found,
     *   the promise should be rejected with \Tuf\Exception\RepoFileNotFound.
     */
    public function load(string $uri, int $maxBytes = null): PromiseInterface;
}

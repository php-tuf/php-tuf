<?php

namespace Tuf\Tests\TestHelpers;

use Psr\Http\Message\StreamInterface;
use Tuf\Loader\LoaderInterface;

class MaxBytesSpyLoader implements LoaderInterface
{
    public array $maxBytes = [];

    public function __construct(private LoaderInterface $decorated)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): StreamInterface
    {
        $this->maxBytes[$uri][] = $maxBytes;
        return $this->decorated->load($uri, $maxBytes);
    }
}

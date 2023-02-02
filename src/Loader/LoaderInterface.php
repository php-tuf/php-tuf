<?php

namespace Tuf\Loader;

use GuzzleHttp\Promise\PromiseInterface;

interface LoaderInterface
{
    public function load(string $uri, int $maxBytes = null): PromiseInterface;
}

<?php

namespace Tuf\Loader;

use Psr\Http\Message\StreamInterface;

/**
 * A data loader that maintains a static cache of already-loaded streams.
 */
class StaticCacheLoader implements LoaderInterface
{
    /**
     * A static cache of already-loaded data streams, keyed by locator.
     *
     * @var \Psr\Http\Message\StreamInterface[]
     */
    private array $cache = [];

    public function __construct(private LoaderInterface $decorated)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $locator, int $maxBytes): StreamInterface
    {
        if (array_key_exists($locator, $this->cache)) {
            return $this->cache[$locator];
        }
        return $this->cache[$locator] = $this->decorated->load($locator, $maxBytes);
    }

    /**
     * Resets the static cache.
     */
    public function reset(): void
    {
        $this->cache = [];
    }
}

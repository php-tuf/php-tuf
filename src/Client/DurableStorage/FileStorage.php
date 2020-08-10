<?php

namespace Tuf\Client\DurableStorage;

/**
 * Defines a simple filesystem-based storage for fetched PHP-TUF metadata.
 *
 * Applications might want to provide an alternative implementation with
 * better performance and error handling.
 *
 * @todo Add tests for this class.
 */
class FileStorage implements \ArrayAccess
{
    /**
     * @var string $basePath
     *   The path on the filesystem to this durable storage's files.
     */
    protected $basePath;

    public function __construct(string $basePath)
    {
        if (! is_dir($basePath)) {
            throw new \RuntimeException("Cannot initialize filesystem local state: \"$basePath\" is not a directory.");
        }

        $this->basePath = $basePath;
    }

    protected function pathWithBasePath($offset)
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $offset;
    }

    public function offsetExists($offset)
    {
        return file_exists($this->pathWithBasePath($offset));
    }

    public function offsetGet($offset)
    {
        return file_get_contents($this->pathWithBasePath($offset));
    }

    public function offsetSet($offset, $value)
    {
        file_put_contents($this->pathWithBasePath($offset), $value);
    }

    public function offsetUnset($offset)
    {
        @unlink($this->pathWithBasePath($offset));
    }
}

<?php

namespace Tuf\Client\DurableStorage;

/**
 * Class FileStorage
 *
 * A simple implementation of \ArrayAccess using the filesystem.
 * Applications might want to provide an alternative implementation with
 * better performance and error handling.
 *
 * @TODO Add tests for this class.
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

    /**
     * Computes the path on the filesystem corresponding to an array key.
     *
     * @param $offset
     *   The array key, or offset as \ArrayAccess calls it.
     * @return string
     */
    protected function pathWithBasePath(string $offset)
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $offset;
    }

    /**
     * Implements \ArrayAccess::offsetExists()
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return file_exists($this->pathWithBasePath($offset));
    }

    /**
     * Implements \ArrayAccess::offsetGet()
     *
     * @param mixed $offset
     * @return false|mixed|string
     */
    public function offsetGet($offset)
    {
        return file_get_contents($this->pathWithBasePath($offset));
    }

    /**
     * Implements \ArrayAccess::offsetSet()
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        file_put_contents($this->pathWithBasePath($offset), $value);
    }

    /**
     * Implements \ArrayAccess::offsetUnset()
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        @unlink($this->pathWithBasePath($offset));
    }
}

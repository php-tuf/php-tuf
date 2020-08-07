<?php

namespace Tuf\Client\DurableStorage;

/**
 * Class FilesystemLocalState
 *
 * A simple implementation of LocalStateInterface using the filesystem.
 * Applications might want to provide an alternative implementation with
 * better performance and error handling.
 *
 * @TODO Add tests for this class.
 */
class FilesystemDurableStorage implements \ArrayAccess
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

    protected function offsetToPath($offset)
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
        return file_exists($this->offsetToPath($offset));
    }

    /**
     * Implements \ArrayAccess::offsetGet()
     *
     * @param mixed $offset
     * @return false|mixed|string
     */
    public function offsetGet($offset)
    {
        return file_get_contents($this->offsetToPath($offset));
    }

    /**
     * Implements \ArrayAccess::offsetSet()
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        file_put_contents($this->offsetToPath($offset), $value);
    }

    /**
     * Implements \ArrayAccess::offsetUnset()
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        @unlink($this->offsetToPath($offset));
    }
}

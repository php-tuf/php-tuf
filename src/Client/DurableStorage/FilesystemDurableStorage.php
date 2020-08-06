<?php


namespace Tuf\Client\DurableStorage;

/**
 * Class FilesystemLocalState
 *
 * A simple implementation of LocalStateInterface using the filesystem.
 * Applications might want to provide an alternative implementation with better performance and error handling.
 */
class FilesystemDurableStorage implements \ArrayAccess
{
    /**
     * @var string $rootPath
     *   The filesystem directory where durable state is rooted.
     */
    protected $rootPath;

    public function __construct(string $rootPath)
    {
        if (! is_dir($rootPath)) {
            throw new \RuntimeException("Cannot initialize filesystem local state: \"$rootPath\" is not a directory.");
        }

        $this->rootPath = $rootPath;
    }

    protected function offsetToPath($offset)
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . $offset;
    }

    public function offsetExists($offset)
    {
        return file_exists($this->offsetToPath($offset));
    }

    public function offsetGet($offset)
    {
        return file_get_contents($this->offsetToPath($offset));
    }

    public function offsetSet($offset, $value)
    {
        file_put_contents($this->offsetToPath($offset), $value);
    }

    public function offsetUnset($offset)
    {
        @unlink($this->offsetToPath($offset));
    }
}

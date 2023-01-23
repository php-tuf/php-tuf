<?php

namespace Tuf\Client\DurableStorage;

use Tuf\Metadata\StorageBase;

/**
 * Defines a simple filesystem-based storage for fetched PHP-TUF metadata.
 *
 * Applications might want to provide an alternative implementation with
 * better performance and error handling.
 */
class FileStorage extends StorageBase implements \ArrayAccess
{
    /**
     * @var string $basePath
     *     The path on the filesystem to this durable storage's files.
     */
    protected $basePath;

    /**
     * Constructs a new FileStorage instance.
     *
     * @param string $basePath
     *     The path on the filesystem to this durable storage's files.
     *
     * @throws \RuntimeException
     *     Thrown if the base path is not an accessible, existing directory.
     */
    public function __construct(string $basePath)
    {
        if (! is_dir($basePath)) {
            throw new \RuntimeException("Cannot initialize filesystem local state: '$basePath' is not a directory.");
        }

        $this->basePath = $basePath;
    }

    /**
     * Returns a full path for an item in the storage.
     *
     * @param mixed $offset
     *     The ArrayAccess offset for the item.
     *
     * @return string
     *     The full path for the item in the storage.
     */
    protected function pathWithBasePath($offset): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $offset;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return file_exists($this->pathWithBasePath($offset));
    }

    protected function read(string $name): ?string
    {
        return isset($this["$name.json"]) ? $this["$name.json"] : null;
    }

    protected function write(string $name, string $data): void
    {
        $this["$name.json"] = $data;
    }

    public function delete(string $name): void
    {
        unset($this["$name.json"]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): mixed
    {
        return file_get_contents($this->pathWithBasePath($offset));
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        file_put_contents($this->pathWithBasePath($offset), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        @unlink($this->pathWithBasePath($offset));
    }
}

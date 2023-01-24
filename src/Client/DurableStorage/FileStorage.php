<?php

namespace Tuf\Client\DurableStorage;

use Tuf\Metadata\StorageBase;

/**
 * Defines a simple filesystem-based storage for fetched PHP-TUF metadata.
 *
 * Applications might want to provide an alternative implementation with
 * better performance and error handling.
 */
class FileStorage extends StorageBase
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

    protected function read(string $name): ?string
    {
        $path = $this->pathWithBasePath("$name.json");
        return file_exists($path) ? file_get_contents($path) : null;
    }

    protected function write(string $name, string $data): void
    {
        file_put_contents($this->pathWithBasePath("$name.json"), $data);
    }

    public function delete(string $name): void
    {
        @unlink($this->pathWithBasePath("$name.json"));
    }
}

<?php

namespace Tuf\Tests\TestHelpers\DurableStorage;

use Tuf\Metadata\StorageBase;

/**
 * Defines a memory storage for trusted TUF metadata, used for testing.
 */
class TestStorage extends StorageBase
{
    private $container = [];

    private $exceptionOnChange = false;

    public static function createFromDirectory(string $dir): static
    {
        $storage = new static();

        // Loop through and load files in the given path.
        $fsIterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME);
        foreach ($fsIterator as $filename => $info) {
            // Only load JSON files.
            /** @var $info \SplFileInfo */
            if ($info->isFile() && $info->getExtension() === 'json') {
                $storage->write($info->getBasename('.json'), file_get_contents($info->getRealPath()));
            }
        }
        return $storage;
    }

    /**
     * Sets whether an exception should be thrown if an call to change storage is made.
     *
     * @param boolean $exceptionOnChange
     *   Whether an exception should be thrown on call to change values.
     *
     * @return void
     */
    public function setExceptionOnChange(bool $exceptionOnChange = true): void
    {
        $this->exceptionOnChange = $exceptionOnChange;
    }

    public function read(string $name): ?string
    {
        return $this->container[$name] ?? null;
    }

    public function write(string $name, string $data): void
    {
        if ($this->exceptionOnChange) {
            throw new \LogicException("Unexpected attempt to change client storage.");
        }
        $this->container[$name] = $data;
    }

    public function delete(string $name): void
    {
        if ($this->exceptionOnChange) {
            throw new \LogicException("Unexpected attempt to change client storage.");
        }
        unset($this->container[$name]);
    }
}

<?php


namespace Tuf\Tests\TestHelpers\DurableStorage;

use Tuf\Metadata\StorageBase;

/**
 * Class MemoryStorage
 *
 * Provides a trivial implementation of \ArrayAccess for testing.
 * Brought to you by https://www.php.net/manual/en/class.arrayaccess and
 * the letters c,t,r,l and v.
 */
class MemoryStorage extends StorageBase
{
    private $container = [];

    private $exceptionOnChange = false;

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

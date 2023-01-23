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
class MemoryStorage extends StorageBase implements \ArrayAccess
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
    public function offsetSet($offset, $value): void
    {
        if ($this->exceptionOnChange) {
            throw new \LogicException("Unexpected attempt to change client storage.");
        }
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        if ($this->exceptionOnChange) {
            throw new \LogicException("Unexpected attempt to change client storage.");
        }
        unset($this->container[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}

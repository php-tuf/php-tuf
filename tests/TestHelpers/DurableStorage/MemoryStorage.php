<?php


namespace Tuf\Tests\TestHelpers\DurableStorage;

/**
 * Class MemoryStorage
 *
 * Provides a trivial implementation of \ArrayAccess for testing.
 * Brought to you by https://www.php.net/manual/en/class.arrayaccess and
 * the letters c,t,r,l and v.
 */
class MemoryStorage implements \ArrayAccess
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

    /**
     * Constructs a new MemoryStorage instance.
     */
    public function __construct()
    {
        $this->container = [];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
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
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        if ($this->exceptionOnChange) {
            throw new \LogicException("Unexpected attempt to change client storage.");
        }
        unset($this->container[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}

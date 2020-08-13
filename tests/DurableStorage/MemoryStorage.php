<?php


namespace Tuf\Tests\DurableStorage;

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

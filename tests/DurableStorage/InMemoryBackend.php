<?php


namespace Tuf\Tests\DurableStorage;

/**
 * Class InMemoryBackend
 *
 * Provides a trivial implementation of \ArrayAccess for testing.
 * Brought to you by https://www.php.net/manual/en/class.arrayaccess and ctrl+v.
 */
class InMemoryBackend implements \ArrayAccess
{
    private $container = array();

    public function __construct()
    {
        $this->container = array();
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}

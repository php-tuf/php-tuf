<?php


namespace Tuf\Client\DurableStorage;

/**
 * Class ValidatingArrayAccessAdapter
 *
 * Thin wrapper around ArrayAccess inserted between tuf and the backend \ArrayAccess to limit the valid inputs.
 */
class ValidatingArrayAccessAdapter implements \ArrayAccess
{
    /**
     * @var \ArrayAccess $backend
     */
    protected $backend;

    public function __construct(\ArrayAccess $backend)
    {
        $this->backend = $backend;
    }

    protected function throwIfInvalidOffset($offset)
    {
        // The offset validation is meant as a security measure to reduce likelihood of undesired backend behavior.
        // For example, a filesystem-backed backend can't be tricked into working in a different directory.
        if (! is_string($offset) || !preg_match("|^[\w._-]+$|", $offset)) {
            throw new \OutOfBoundsException("Array offset \"$offset\" is not a valid durable storage key.");
        }
    }

    public function offsetExists($offset)
    {
        $this->throwIfInvalidOffset($offset);
        return $this->backend->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        $this->throwIfInvalidOffset($offset);
        return $this->backend->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->throwIfInvalidOffset($offset);
        // @todo Consider enforcing an application-configurable maximum length
        //     here. https://github.com/php-tuf/php-tuf/issues/27
        if (! is_string($value)) {
            $format = "Cannot store %s at offset $offset: only strings are allowed in durable storage.";
            throw new \UnexpectedValueException(sprintf($format, gettype($value)));
        }
        $this->backend->offsetSet($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->throwIfInvalidOffset($offset);
        $this->backend->offsetUnset($offset);
    }
}

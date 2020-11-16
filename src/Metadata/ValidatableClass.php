<?php

namespace Tuf\Metadata;

/**
 * Defines a class that can used with Symfony validator constraints.
 *
 * Symfony validator constraints do not work with classes that have dynamic
 * public properties.
 */
final class ValidatableClass implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * The current key to implement \Iterator.
     * @var string
     */
    private $internalCurrentKey;

    /**
     * ValidatableClass constructor.
     *
     * @param \stdClass $class
     *   The class to use.
     */
    public function __construct(\stdClass $class)
    {
        // Transfer all the public properties to this class.
        $reflection = new \ReflectionObject($class);
        $publicProperties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($publicProperties as $publicProperty) {
            $this->{$publicProperty->getName()}  = $publicProperty->getValue($class);
        }
        $this->rewind();
    }


    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return in_array($offset, $this->getPublicPropertyNames());
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        $this->validateOffset($offset);
        $value = $this->{$offset};
        if (is_object($value)) {
            return clone $value;
        }
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->validateOffset($offset);
        $this->{$offset} = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException("don't unset");
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->offsetGet($this->internalCurrentKey);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $properties = $this->getPublicPropertyNames();
        $index = array_search($this->internalCurrentKey, $properties);
        $index++;
        if ($index >= count($properties)) {
            $this->internalCurrentKey = null;
            return false;
        }
        $this->internalCurrentKey = $properties[$index];
        return $this->offsetGet($this->internalCurrentKey);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->internalCurrentKey;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return in_array($this->internalCurrentKey, $this->getPublicPropertyNames());
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $properties = $this->getPublicPropertyNames();
        $this->internalCurrentKey = array_shift($properties);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->getPublicPropertyNames());
    }

    /**
     * Validate the offset is not a non-public property of this class.
     *
     * @param string $offset
     *   The offset.
     *
     * @return void
     */
    private function validateOffset(string $offset): void
    {
        if ($offset === 'internalCurrentKey') {
            throw new \RuntimeException("Cannot used reserved property name '$offset'");
        }
    }

    /**
     * Gets the names of the public property of this class.
     *
     * @return array
     *   The names of the public properties.
     */
    private function getPublicPropertyNames(): array
    {
        $reflection = new \ReflectionObject($this);
        $publicProperties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $propertyNames = [];
        foreach ($publicProperties as $publicProperty) {
            $propertyNames[] = $publicProperty->getName();
        }
        return $propertyNames;
    }
}

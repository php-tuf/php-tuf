<?php

namespace Tuf\Metadata;

use function DeepCopy\deep_copy;

/**
 * Defines a class that can replace \stdClass to work with Symfony validator constraints.
 *
 * Symfony validator constraints do not work with classes that have dynamic
 * public properties.
 */
final class ValidatableClass extends \ArrayObject
{

    /**
     * {@inheritdoc}
     */
    public function __construct($input = [], $flags = 0, $iteratorClass = "ArrayIterator")
    {
        if (get_class($input) !== \stdClass::class) {
            new \RuntimeException("ValidatableClass is only intended to used as replace for stdClass");
        }
        parent::__construct($input, $flags, $iteratorClass);
    }


    /**
     * {@inheritdoc}
     */
    public function offsetGet($index, $getOrignal = false)
    {
        $value = parent::offsetGet($index);
        return $getOrignal ? parent::offsetGet($index) : deep_copy($value);
    }
}

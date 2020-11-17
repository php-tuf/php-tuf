<?php

namespace Tuf\Metadata;

use function DeepCopy\deep_copy;

/**
 * Defines a class that can used with Symfony validator constraints.
 *
 * Symfony validator constraints do not work with classes that have dynamic
 * public properties.
 */
final class ValidatableClass extends \ArrayObject
{

    /**
     * {@inheritdoc}
     */
    public function offsetGet($index)
    {
        return deep_copy(parent::offsetGet($index));
    }


}

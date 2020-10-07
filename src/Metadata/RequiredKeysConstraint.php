<?php


namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraint;

class RequiredKeysConstraint extends Constraint
{
    public $requiredKeys;

    public $message = 'The following keys "{{ keys }}" are required';

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'requiredKeys';
    }
}

<?php

namespace Tuf\Constraints;

use Symfony\Component\Validator\Constraints\Collection as SymfonyCollection;

/**
 * Custom constraint that extends Symfony's Collection constraint to add 'excludedFields'.
 */
class Collection extends SymfonyCollection
{

    /**
     * @var array
     */
    public $unsupportedFields = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        if (isset($options['excludedFields']) && !isset($options['fields'])) {
            throw new \InvalidArgumentException('If "excludedFields" is set "fields" must also be set.');
        }
        parent::__construct($options);
    }
}

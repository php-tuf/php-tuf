<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\TargetsMetadata;

class TargetsMetadataTest extends MetaDataBaseTest
{

    /**
     * {@inheritdoc}
     */
    protected $validJson = '1.targets.json';

    /**
     * {@inheritdoc}
     */
    protected $testClass = TargetsMetadata::class;

    /**
     * {@inheritdoc}
     */
    protected $expectedType = 'targets';

    /**
     * {@inheritdoc}
     */
    public function providerExpectedField() : array
    {
        $data = parent::providerExpectedField();
        // @todo Add targets specifics.
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidField() : array
    {
        $data = parent::providerValidField();
        // @todo Add targets specifics.
        return $data;
    }
}

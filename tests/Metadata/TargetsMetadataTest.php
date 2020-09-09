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

    public function providerExpectedField()
    {
        $data = parent::providerExpectedField();
        // @todo Add targets specifics.
        return $data;
    }

    public function providerValidField()
    {
        $data = parent::providerValidField();
        // @todo Add targets specifics.
        return $data;
    }
}

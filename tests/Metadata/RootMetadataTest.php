<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\RootMetadata;

class RootMetadataTest extends MetaDataBaseTest
{

    /**
     * {@inheritdoc}
     */
    protected $validJson = '1.root.json';

    /**
     * {@inheritdoc}
     */
    protected $testClass = RootMetadata::class;

    /**
     * {@inheritdoc}
     */
    protected $expectedType = 'root';

    public function providerExpectedField()
    {
        $data = parent::providerExpectedField();
        // @todo Add root specifics.
        return $data;
    }

    public function providerValidField()
    {
        $data = parent::providerValidField();
        // @todo Add root specifics.
        return $data;
    }
}

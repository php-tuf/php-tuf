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
    protected $expectedType = 'targets';

    /**
     * {@inheritdoc}
     */
    protected static function callCreateFromJson(string $json)
    {
        TargetsMetadata::createFromJson($json);
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidMetaData()
    {
        return [
            '1.targets.json' => ['1.targets.json'],
            '3.targets.json' => ['3.targets.json'],
        ];
    }


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

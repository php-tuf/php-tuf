<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\TargetsMetadata;

class TargetsMetadataTest extends MetaDataBaseTest
{

    /**
     * {@inheritdoc}
     */
    protected $validJson = '3.targets.json';


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
        $data[] = ['signed:delegations'];
        $data[] = ['signed:delegations:keys'];
        $data[] = ['signed:delegations:roles'];
        $data[] = ['signed:delegations:roles:0:keyids'];
        $data[] = ['signed:delegations:roles:0:name'];
        $data[] = ['signed:delegations:roles:0:paths'];
        $data[] = ['signed:delegations:roles:0:terminating'];
        $data[] = ['signed:delegations:roles:0:threshold'];
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

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
    protected $expectedType = 'root';

    /**
     * {@inheritdoc}
     */
    protected static function callCreateFromJson(string $json) : void
    {
        RootMetadata::createFromJson($json);
    }

    /**
     * {@inheritdoc}
     */
    public function providerExpectedField() : array
    {
        $data = parent::providerExpectedField();

        $data[] = ['signed:keys'];
        $firstKey = $this->getFixtureNestedArrayFirstKey('1.root.json', ['signed', 'keys']);
        $data[] = ["signed:keys:$firstKey:keyid_hash_algorithms"];
        $data[] = ["signed:keys:$firstKey:keytype"];
        $data[] = ["signed:keys:$firstKey:keyval"];
        $data[] = ["signed:keys:$firstKey:scheme"];
        $data[] = ['signed:roles'];
        $data[] = ['signed:roles:targets:keyids'];
        $data[] = ['signed:roles:targets:threshold'];
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidField() : array
    {
        $data = parent::providerValidField();
        $firstKey = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'keys']);
        $data[] = ["signed:keys:$firstKey:keyid_hash_algorithms", 'array'];
        $data[] = ["signed:keys:$firstKey:keytype", 'string'];
        $data[] = ["signed:keys:$firstKey:keyval", 'array'];
        $data[] = ["signed:keys:$firstKey:keyval:public", 'string'];
        $data[] = ["signed:keys:$firstKey:scheme", 'string'];
        $data[] = ['signed:roles', 'array'];
        $data[] = ['signed:roles:targets:keyids', 'array'];
        $data[] = ['signed:roles:targets:threshold', 'int'];
        return $data;
    }
}

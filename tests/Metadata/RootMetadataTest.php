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
    protected static function callCreateFromJson(string $json)
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
        $firstKey = $this->getFixtureFirstKey();
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
        $firstKey = $this->getFixtureFirstKey();
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

    /**
     * Determines the first key for the 'keys' element to avoid the test
     * breaking every time the test fixtures are recreated.
     *
     * @return string
     *   The first key.
     */
    private function getFixtureFirstKey(): string
    {
        $realPath = static::getFixturesRealPath('tufclient/tufrepo/metadata/current/1.root.json', false);
        $root = json_decode(file_get_contents($realPath), true);
        $keys = array_keys($root['signed']['keys']);
        return array_shift($keys);
    }
}

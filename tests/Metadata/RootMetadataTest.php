<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
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

    /**
     * Tests that an exception will thrown if a required role is missing.
     *
     * @param string $missingRole
     *   The required role to test.
     *
     * @return void
     *
     * @dataProvider providerRequireRoles
     */
    public function testRequiredRoles(string $missingRole)
    {
        $this->expectException(MetadataException::class);
        $expectedMessage = preg_quote("Array[signed][roles][$missingRole]:", '/');
        $expectedMessage .= '.*This field is missing';
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        $data = json_decode($this->localRepo[$this->validJson], true);
        unset($data['signed']['roles'][$missingRole]);
        static::callCreateFromJson(json_encode($data));
    }

    /**
     * Dataprovider for testRequiredRoles().
     *
     * @return string[][]
     *   The test cases.
     */
    public function providerRequireRoles()
    {
        return static::getKeyedArray([
            ['root'],
            ['timestamp'],
            ['snapshot'],
            ['targets'],
        ]);
    }

    /**
     * Tests that an optional 'mirror' role is allowed.
     *
     * @return void
     */
    public function testOptionalMirrorRole()
    {
        $this->expectNotToPerformAssertions();
        $data = json_decode($this->localRepo[$this->validJson], true);
        $data['signed']['roles']['mirror'] = $data['signed']['roles']['root'];
        static::callCreateFromJson(json_encode($data));
    }

    /**
     * Tests that an unknown role name is not allowed.
     *
     * @return void
     */
    public function testInvalidRoleName()
    {
        $this->expectException(MetadataException::class);
        $expectedMessage = preg_quote("Array[signed][roles][super_root]:", '/');
        $expectedMessage .= '.*This field was not expected';
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        $data = json_decode($this->localRepo[$this->validJson], true);
        $data['signed']['roles']['super_root'] = $data['signed']['roles']['root'];
        static::callCreateFromJson(json_encode($data));
    }
}

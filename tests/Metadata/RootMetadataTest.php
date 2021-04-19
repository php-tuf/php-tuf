<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\RootMetadata;

/**
 * @coversDefaultClass \Tuf\Metadata\RootMetadata
 */
class RootMetadataTest extends MetadataBaseTest
{

    use UntrustedExceptionTrait;
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
    protected static function callCreateFromJson(string $json): MetadataBase
    {
        return RootMetadata::createFromJson($json);
    }

    /**
     * {@inheritdoc}
     */
    public function providerExpectedField(): array
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
        $data[] = ['signed:consistent_snapshot'];
        return $this->getKeyedArray($data);
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidField(): array
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
        $data[] = ['signed:consistent_snapshot', 'boolean'];
        return $this->getKeyedArray($data);
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
    public function testRequiredRoles(string $missingRole): void
    {
        $this->expectException(MetadataException::class);
        $expectedMessage = preg_quote("Object(ArrayObject)[signed][roles][$missingRole]:", '/');
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
    public function providerRequireRoles(): array
    {
        return static::getKeyedArray([
            ['root'],
            ['timestamp'],
            ['snapshot'],
            ['targets'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function providerOptionalFields(): array
    {
        $data = parent::providerOptionalFields();
        $data[] = [
            'signed:roles:mirror',
            [
                'keyids' => ['76b9ae56adaeebe44ebfd4e73c57bb68e920ee046ff03c6f7e1424a9078af785'],
                'threshold' => 1,
            ],
        ];
        return static::getKeyedArray($data, 0);
    }

    /**
     * Tests that an unknown role name is not allowed.
     *
     * @return void
     */
    public function testInvalidRoleName(): void
    {
        $this->expectException(MetadataException::class);
        $expectedMessage = preg_quote("Object(ArrayObject)[signed][roles][super_root]:", '/');
        $expectedMessage .= '.*This field was not expected';
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        $data = json_decode($this->localRepo[$this->validJson], true);
        $data['signed']['roles']['super_root'] = $data['signed']['roles']['root'];
        static::callCreateFromJson(json_encode($data));
    }

    /**
     * @covers ::supportsConsistentSnapshots
     *
     * @return void
     */
    public function testSupportsConsistentSnapshots(): void
    {
        $data = json_decode($this->localRepo[$this->validJson], true);
        foreach ([true, false] as $value) {
            $data['signed']['consistent_snapshot'] = $value;
            /** @var \Tuf\Metadata\RootMetadata $metadata */
            $metadata = static::callCreateFromJson(json_encode($data));
            $metadata->setIsTrusted(true);
            $this->assertSame($value, $metadata->supportsConsistentSnapshots());
        }
    }

    /**
     * Data provider for testUntrustedException().
     *
     * @return string[]
     *   The test cases for testUntrustedException().
     */
    public function providerUntrustedException(): array
    {
        return self::getKeyedArray([
            ['supportsConsistentSnapshots'],
            ['getKeys'],
            ['getRoles'],
        ]);
    }

    /**
     * @covers ::getRoles
     */
    public function testGetRoles(): void
    {
        $json = $this->localRepo[$this->validJson];
        $data = json_decode($json, true);
        /** @var \Tuf\Metadata\RootMetadata $metadata */
        $metadata = static::callCreateFromJson($json);
        $metadata->setIsTrusted(true);
        $expectRoleNames = ['root', 'snapshot', 'targets', 'timestamp'];
        $roles = $metadata->getRoles();
        self::assertCount(4, $roles);
        foreach ($expectRoleNames as $expectRoleName) {
            self::assertSame($data['signed']['roles'][$expectRoleName]['threshold'], $roles[$expectRoleName]->getThreshold());
            self::assertSame($expectRoleName, $roles[$expectRoleName]->getName());
            foreach ($data['signed']['roles'][$expectRoleName]['keyids'] as $keyId) {
                self::assertTrue($roles[$expectRoleName]->isKeyIdAcceptable($keyId));
            }
            self::assertFalse($roles[$expectRoleName]->isKeyIdAcceptable('nobodys_key'));
        }
    }
}

<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\NotFoundException;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\TargetsMetadata;

/**
 * @coversDefaultClass \Tuf\Metadata\TargetsMetadata
 */
class TargetsMetadataTest extends MetadataBaseTest
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
    protected static function callCreateFromJson(string $json, string $role = null): MetadataBase
    {
        return TargetsMetadata::createFromJson($json, $role);
    }

    /**
     * @covers ::getHashes
     * @covers ::getLength
     *
     * @return void
     *   Describe the void.
     */
    public function testGetHashesAndLength(): void
    {
        $json = $this->localRepo[$this->validJson];
        $metadata = TargetsMetadata::createFromJson($json);
        $json = json_decode($json, true);

        $target = key($json['signed']['targets']);
        $this->assertSame($metadata->getHashes($target)->getArrayCopy(), $json['signed']['targets'][$target]['hashes']);
        $this->assertSame($metadata->getLength($target), $json['signed']['targets'][$target]['length']);

        // Trying to get information about an unknown target should throw.
        try {
            $metadata->getHashes('void.txt');
            $this->fail('Exception was not thrown for an invalid target.');
        } catch (NotFoundException $e) {
            $this->assertSame("Target not found: void.txt", $e->getMessage());
        }

        try {
            $metadata->getLength('void.txt');
            $this->fail('Exception was not thrown for an invalid target.');
        } catch (NotFoundException $e) {
            $this->assertSame("Target not found: void.txt", $e->getMessage());
        }
    }

    /**
     * @covers ::hasTarget
     */
    public function testHasTarget(): void
    {
        $json = $this->localRepo[$this->validJson];
        $metadata = TargetsMetadata::createFromJson($json);
        $json = json_decode($json, true);

        $target = key($json['signed']['targets']);
        $this->assertTrue($metadata->hasTarget($target));
        $this->assertFalse($metadata->hasTarget('a-file-that-does-not-exist-as-a-target.txt'));
    }

    /**
     * {@inheritdoc}
     */
    public function providerExpectedField(): array
    {
        $data = parent::providerExpectedField();
        $data[] = ['signed:delegations'];
        $data[] = ['signed:delegations:keys'];
        $firstKey = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'delegations', 'keys']);
        $data[] = ["signed:delegations:keys:$firstKey:keyid_hash_algorithms"];
        $data[] = ["signed:delegations:keys:$firstKey:keytype"];
        $data[] = ["signed:delegations:keys:$firstKey:keyval"];
        $data[] = ["signed:delegations:keys:$firstKey:keyval:public"];
        $data[] = ["signed:delegations:keys:$firstKey:scheme"];
        $data[] = ['signed:delegations:roles'];
        $data[] = ['signed:delegations:roles:0:keyids'];
        $data[] = ['signed:delegations:roles:0:name'];
        $data[] = ['signed:delegations:roles:0:paths'];
        $data[] = ['signed:delegations:roles:0:terminating'];
        $data[] = ['signed:delegations:roles:0:threshold'];
        $target = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'targets']);
        $data[] = ["signed:targets:$target:hashes"];
        $data[] = ["signed:targets:$target:length"];
        return static::getKeyedArray($data);
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidField(): array
    {
        $data = parent::providerValidField();
        $target = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'targets']);
        $data[] = ["signed:targets:$target:hashes", 'array'];
        $data[] = ["signed:targets:$target:length", 'int'];
        $data[] = ["signed:targets:$target:custom", '\ArrayObject'];
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerOptionalFields(): array
    {
        $data = parent::providerOptionalFields();
        $target = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'targets']);
        $data[] = ["signed:targets:$target:custom", ['ignored_key' => 'ignored_value']];
        return $data;
    }

    /**
     * @covers ::getDelegatedKeys
     */
    public function testGetDelegatedKeys(): void
    {
        $json = $this->localRepo[$this->validJson];
        $metadata = TargetsMetadata::createFromJson($json);
        $json = json_decode($json, true);
        $keys = $metadata->getDelegatedKeys();
        static::replaceArrayObjects($keys);
        $this->assertSame($json['signed']['delegations']['keys'], $keys);
    }

    /**
     * @covers ::getDelegatedRoles
     */
    public function testGetDelegatedRoles(): void
    {
        $json = $this->localRepo[$this->validJson];
        $metadata = TargetsMetadata::createFromJson($json);
        $json = json_decode($json, true);
        $keys = $metadata->getDelegatedRoles();
        static::replaceArrayObjects($keys);
        $this->assertSame($json['signed']['delegations']['roles'], $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function testGetRole(): void
    {
        parent::testGetRole();
        // Confirm that if a role name is specified this will be returned.
        $metadata = static::callCreateFromJson($this->localRepo[$this->validJson], 'other_role');
        $this->assertSame('other_role', $metadata->getRole());
    }
}

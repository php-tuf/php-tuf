<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
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
    protected $validJson = '2.targets';


    /**
     * {@inheritdoc}
     */
    protected $expectedType = 'targets';

    /**
     * {@inheritdoc}
     */
    protected static function callCreateFromJson(string $json, ?string $role = null): MetadataBase
    {
        return TargetsMetadata::createFromJson($json, $role);
    }

    /**
     * @covers ::getHashes
     * @covers ::getLength
     */
    public function testGetHashesAndLength(): void
    {
        $json = $this->clientStorage->read($this->validJson);
        $metadata = TargetsMetadata::createFromJson($json);
        $json = static::decodeJson($json);

        $target = key($json['signed']['targets']);
        $this->assertSame($metadata->getHashes($target), $json['signed']['targets'][$target]['hashes']);
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
        $json = $this->clientStorage->read($this->validJson);
        $metadata = TargetsMetadata::createFromJson($json);
        $json = static::decodeJson($json);

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
        // Delegations are optional (see ::providerOptionalFields()), but if
        // present, test that their internal structure is validated too.
        $data[] = ['signed:delegations:keys'];
        $firstKey = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'delegations', 'keys']);
        $data[] = ["signed:delegations:keys:$firstKey:keytype"];
        $data[] = ["signed:delegations:keys:$firstKey:keyval"];
        $data[] = ["signed:delegations:keys:$firstKey:keyval:public"];
        $data[] = ["signed:delegations:keys:$firstKey:scheme"];
        $data[] = ['signed:delegations:roles'];
        $data[] = ['signed:delegations:roles:0:keyids'];
        $data[] = ['signed:delegations:roles:0:name'];
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
        $data[] = ["signed:targets:$target:custom", 'array'];

        $role = $this->getFixtureNestedArrayFirstKey($this->validJson, ['signed', 'delegations', 'roles']);
        $data[] = ["signed:delegations:roles:$role:paths", 'iterable'];
        $data[] = ["signed:delegations:roles:$role:path_hash_prefixes", 'iterable'];
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
        $data[] = [
            'signed:delegations',
            [
                'keys' => [],
                'roles' => [],
            ],
        ];
        $data[] = [
            'signed:delegations:roles:0:paths',
            ['delegated/path'],
        ];
        return $data;
    }

    /**
     * @covers ::getDelegatedKeys
     */
    public function testGetDelegatedKeys(): void
    {
        $json = $this->clientStorage->read($this->validJson);
        /** @var \Tuf\Metadata\TargetsMetadata $metadata */
        $metadata = TargetsMetadata::createFromJson($json);
        $json = static::decodeJson($json);
        $keys = $metadata->getDelegatedKeys();
        $expectedKeys = $json['signed']['delegations']['keys'];
        self::assertCount(count($expectedKeys), $keys);
        foreach ($keys as $key) {
            $computedId = $key->getComputedKeyId();
            self::assertArrayHasKey($computedId, $expectedKeys);
            self::assertSame($expectedKeys[$computedId]['keytype'], $key->type);
            self::assertSame($expectedKeys[$computedId]['keyval']['public'], $key->public);
        }
    }

    /**
     * @covers ::getDelegatedRoles
     */
    public function testGetDelegatedRoles(): void
    {
        $json = $this->clientStorage->read($this->validJson);
        /** @var TargetsMetadata $metadata */
        $metadata = TargetsMetadata::createFromJson($json);
        $json = static::decodeJson($json);
        $delegatedRoles = $metadata->getDelegatedRoles();
        $expectedRoles = $json['signed']['delegations']['roles'];
        self::assertCount(1, $expectedRoles);
        self::assertCount(1, $delegatedRoles);
        foreach ($expectedRoles as $expectedRole) {
            $delegatedRole = $delegatedRoles[$expectedRole['name']];
            self::assertSame($expectedRole['threshold'], $delegatedRole->threshold);
            self::assertSame($expectedRole['name'], $delegatedRole->name);
            foreach ($expectedRole['keyids'] as $keyId) {
                self::assertTrue($delegatedRole->isKeyIdAcceptable($keyId));
            }
            self::assertFalse($delegatedRole->isKeyIdAcceptable('nobodys_key'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function testGetRole(): void
    {
        parent::testGetRole();
        // Confirm that if a role name is specified this will be returned.
        $metadata = static::callCreateFromJson($this->clientStorage->read($this->validJson), 'other_role');
        $this->assertSame('other_role', $metadata->getRole());
    }

    public function testDuplicateDelegatedRoleNames(): void
    {
        $json = $this->clientStorage->read($this->validJson);
        $data = static::decodeJson($json);

        $this->assertNotEmpty($data['signed']['delegations']['roles']);
        // Duplicating a role should raise a validation exception.
        $data['signed']['delegations']['roles'][] = $data['signed']['delegations']['roles'][0];
        $json = static::encodeJson($data);

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Delegated role names must be unique.');
        static::callCreateFromJson($json);
    }

    public function testKeysAreSorted(): void
    {
        $data = [
            'signed' => [
                '_type' => 'targets',
                'version' => 1,
                'targets' => [
                    'foo' => [
                        'custom' => [
                            'z' => 'Yes',
                            'a' => 'No',
                        ],
                    ],
                    'baz' => [],
                ],
                'delegations' => [
                    'keys' => [
                        'z' => [],
                        'a' => [],
                    ],
                ],
            ],
            'signatures' => [],
        ];
        $metadata = new TargetsMetadata($data, '');

        $decoded = static::decodeJson($metadata->toCanonicalJson());
        // The out-of-order keys should have been reordered.
        $this->assertSame(['_type', 'delegations', 'targets', 'version'], array_keys($decoded));
        $this->assertSame(['baz', 'foo'], array_keys($decoded['targets']));
        $this->assertSame(['a', 'z'], array_keys($decoded['targets']['foo']['custom']));
        $this->assertSame(['a', 'z'], array_keys($decoded['delegations']['keys']));
    }

    public function testEmptyStructuresAreEncodedAsObjects(): void
    {
        $data = [
            'signed' => [
                '_type' => 'targets',
                'version' => 1,
                'targets' => [
                    'foo.txt' => [
                        'custom' => [],
                    ],
                    'baz.txt' => [],
                ],
                'delegations' => [
                    'keys' => [],
                ],
            ],
            'signatures' => [],
        ];
        $metadata = new TargetsMetadata($data, '');
        $decoded = json_decode($metadata->toCanonicalJson());
        // Things that should be objects are still objects, despite being empty.
        $this->assertIsObject($decoded->targets->{'foo.txt'}->custom);
        $this->assertIsObject($decoded->delegations->keys);

        $data['signed']['targets'] = [];
        $metadata = new TargetsMetadata($data, '');
        $decoded = json_decode($metadata->toCanonicalJson());
        // Things that should be objects are still objects, despite being empty.
        $this->assertIsObject($decoded->targets);
    }
}

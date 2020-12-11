<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\KeyDB;
use Tuf\Metadata\RootMetadata;
use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * @coversDefaultClass \Tuf\KeyDB
 */
class KeyDBTest extends TestCase
{
    use UtilsTrait;
    /**
     * @covers ::createFromRootMetadata
     *
     * @return void
     */
    public function testCreateFromRootMetadata():void
    {
        $rootJsonPath = static::getFixturesRealPath(
            'TUFTestFixtureDelegated',
            'tufclient/tufrepo/metadata/current/3.root.json',
            false
        );
        $rootMetadata = RootMetadata::createFromJson(file_get_contents($rootJsonPath));
        self::assertInstanceOf(RootMetadata::class, $rootMetadata);
        $keyDb = KeyDB::createFromRootMetadata($rootMetadata);
        self::assertInstanceOf(KeyDB::class, $keyDb);
        $keyId256 = '77dfdca206c0fe1b8e55d67d21dd0e195a0998a9d2b56c6d3ee8f68d04c21e93';
        $keyId512 = 'ae0bd43afa380e413c34ca6a51092d9b0bb81e8e4913dfeb137bb1b7f23fa6cb7f32a104b8e13eab438cd9198c09a5115753d9315c23cf3230093424e19be694';
        $expectedKeyData = [
            'keyid_hash_algorithms' => [
                'sha256',
                'sha512',
            ],
            'keytype' => 'ed25519',
            'keyval' => new \ArrayObject(['public' => '6400d770c7c1bce4b3d59ce0079ed686e843b6500bbea77d869a1ae7df4565a1']),
            'scheme' => 'ed25519',
        ];
        $expectedKeyDataObject = new \ArrayObject($expectedKeyData);
        $key256 = $keyDb->getKey($keyId256);
        $key512 = $keyDb->getKey($keyId512);
        self::assertEquals($expectedKeyDataObject, $key256);
        self::assertEquals($expectedKeyDataObject, $key512);
        // Ensure that changing a value in the key does not affect the internal state of the KeyDB object.
        $key256['keyval']['new_key'] = 'new_value';
        $key512['keyval']['new_key'] = 'new_value';
        self::assertEquals($expectedKeyDataObject, $keyDb->getKey($keyId256));
        self::assertEquals($expectedKeyDataObject, $keyDb->getKey($keyId512));
    }
}

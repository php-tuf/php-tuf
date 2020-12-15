<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\JsonNormalizer;
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
        // Get the first key for comparison.
        $key = $rootMetadata->getKeys()->getIterator()->current();
        $jsonEncodedKey = json_encode($key);
        // Create the 2 hashed versions of the key id which can be used by getKey().
        $keyNormalized = JsonNormalizer::asNormalizedJson($key);
        $keyId256 = hash('sha256', $keyNormalized);
        $keyId512 = hash('sha512', $keyNormalized);
        self::assertSame($jsonEncodedKey, json_encode($keyDb->getKey($keyId256)));
        self::assertSame($jsonEncodedKey, json_encode($keyDb->getKey($keyId512)));

        // Ensure that changing a value in the key does not affect the internal state of the KeyDB object.
        $key256['keyval']['new_key'] = 'new_value';
        $key512['keyval']['new_key'] = 'new_value';
        self::assertSame($jsonEncodedKey, json_encode($keyDb->getKey($keyId256)));
        self::assertSame($jsonEncodedKey, json_encode($keyDb->getKey($keyId512)));
    }
}

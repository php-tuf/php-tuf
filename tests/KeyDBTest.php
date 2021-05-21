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
    public function testCreateFromRootMetadata(): void
    {
        $rootJsonPath = static::getFixturesRealPath(
            'TUFTestFixtureDelegated',
            'tufclient/tufrepo/metadata/current/3.root.json',
            false
        );
        $rootMetadata = RootMetadata::createFromJson(file_get_contents($rootJsonPath));
        self::assertInstanceOf(RootMetadata::class, $rootMetadata);
        $rootMetadata->trust();
        $keyDb = KeyDB::createFromRootMetadata($rootMetadata);
        self::assertInstanceOf(KeyDB::class, $keyDb);
        // Get the first key for comparison.
        $keys = $rootMetadata->getKeys();
        $key = reset($keys);
        $retrievedKey = $keyDb->getKey($key->getComputedKeyId());
        // Ensure the retrieved key is the same.
        self::assertSame($key->getPublic(), $retrievedKey->getPublic());
        self::assertSame($key->getType(), $retrievedKey->getType());
        self::assertSame($key->getComputedKeyId(), $retrievedKey->getComputedKeyId());
    }
}

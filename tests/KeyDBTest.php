<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\KeyDB;
use Tuf\Metadata\RootMetadata;
use Tuf\Tests\TestHelpers\FixturesTrait;

/**
 * @coversDefaultClass \Tuf\KeyDB
 */
class KeyDBTest extends TestCase
{
    use FixturesTrait;
    use ProphecyTrait;

    /**
     * @covers ::createFromRootMetadata
     *
     * @return void
     */
    public function testCreateFromRootMetadata(): void
    {
        $rootJsonPath = static::getFixturePath(
            'Delegated/consistent',
            'client/metadata/current/2.root.json',
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

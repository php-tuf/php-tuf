<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Client\SignatureVerifier;
use Tuf\Metadata\RootMetadata;
use Tuf\Tests\TestHelpers\FixturesTrait;

/**
 * @coversDefaultClass \Tuf\Client\SignatureVerifier
 */
class SignatureVerifierTest extends TestCase
{
    use FixturesTrait;
    use ProphecyTrait;

    /**
     * @covers ::createFromRootMetadata
     * @covers ::addKey
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

        $verifier = SignatureVerifier::createFromRootMetadata($rootMetadata);
        $verifierKeys = new \ReflectionProperty($verifier, 'keys');
        $verifierKeys->setAccessible(TRUE);
        $verifierKeys = $verifierKeys->getValue($verifier);

        // Get the first key for comparison.
        $keys = $rootMetadata->getKeys();
        $key = reset($keys);
        $this->assertArrayHasKey($key->getComputedKeyId(), $verifierKeys);
        $retrievedKey = $verifierKeys[key($keys)];
        // Ensure the retrieved key is the same.
        self::assertSame($key->getPublic(), $retrievedKey->getPublic());
        self::assertSame($key->getType(), $retrievedKey->getType());
        self::assertSame($key->getComputedKeyId(), $retrievedKey->getComputedKeyId());
    }
}

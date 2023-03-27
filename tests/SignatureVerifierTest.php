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
        $rootMetadata = RootMetadata::createFromJson(file_get_contents($rootJsonPath))
            ->trust();

        $verifier = SignatureVerifier::createFromRootMetadata($rootMetadata);
        $verifierKeys = new \ReflectionProperty($verifier, 'keys');
        $verifierKeys->setAccessible(true);
        $verifierKeys = $verifierKeys->getValue($verifier);

        // All of the root metadata keys should be loaded into the verifier.
        foreach ($rootMetadata->getKeys() as $keyId => $key) {
            $this->assertArrayHasKey($keyId, $verifierKeys);
            self::assertSame($key->getPublic(), $verifierKeys[$keyId]->getPublic());
            self::assertSame($key->getType(), $verifierKeys[$keyId]->getType());
            self::assertSame($key->getComputedKeyId(), $verifierKeys[$keyId]->getComputedKeyId());
        }
    }
}

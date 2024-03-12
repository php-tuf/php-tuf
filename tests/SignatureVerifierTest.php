<?php

namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\Client\SignatureVerifier;
use Tuf\Exception\NotFoundException;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\TimestampMetadata;
use Tuf\Tests\TestHelpers\FixturesTrait;

/**
 * @coversDefaultClass \Tuf\Client\SignatureVerifier
 */
class SignatureVerifierTest extends TestCase
{
    use FixturesTrait;

    /**
     * @covers ::createFromRootMetadata
     * @covers ::addKey
     */
    public function testCreateFromRootMetadata(): void
    {
        $rootJsonPath = static::getFixturePath(
            'Delegated/consistent',
            'client/2.root.json',
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
            self::assertSame($key->public, $verifierKeys[$keyId]->public);
            self::assertSame($key->type, $verifierKeys[$keyId]->type);
            self::assertSame($key->getComputedKeyId(), $verifierKeys[$keyId]->getComputedKeyId());
        }
    }

    /**
     * @covers ::checkSignatures
     */
    public function testCheckSignatureWithInvalidRole(): void
    {
        $fixturePath = static::getFixturePath('Simple/consistent', 'server');

        $rootMetadata = file_get_contents($fixturePath . '/1.root.json');
        $rootMetadata = RootMetadata::createFromJson($rootMetadata)->trust();

        $timestampMetadata = $this->createMock(TimestampMetadata::class);
        $timestampMetadata->expects($this->atLeastOnce())
            ->method('getRole')
            ->willReturn('unknown');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("role not found: unknown");
        SignatureVerifier::createFromRootMetadata($rootMetadata)
            ->checkSignatures($timestampMetadata);
    }
}

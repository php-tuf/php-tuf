<?php

namespace Tuf\Tests\Unit;

use Tuf\Key;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Tuf\Key
 */
class KeyTest extends TestCase
{
    /**
     * @covers ::createFromMetadata
     * @covers ::getComputedKeyId
     */
    public function testCreateFromMetadata(): void
    {
        $data = [
            'keytype' => 'ed25519',
            'scheme' => 'scheme-ed11111',
            'keyval' => ['public' => '12345'],
        ];
        $key = Key::createFromMetadata($data);
        self::assertInstanceOf(Key::class, $key);
        self::assertSame('ed25519', $key->type);
        self::assertSame('12345', $key->public);
        $keySortedCanonicalStruct = [
            'keytype' => 'ed25519',
            'keyval' => ['public' => '12345'],
            'scheme' => 'scheme-ed11111',
        ];
        $keyCanonicalForm = json_encode($keySortedCanonicalStruct, JSON_UNESCAPED_SLASHES);

        self::assertSame(hash('sha256', $keyCanonicalForm), $key->getComputedKeyId());
    }
}

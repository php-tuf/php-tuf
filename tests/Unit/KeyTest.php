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
     * @covers ::getPublic
     * @covers ::getType
     *
     * @dataProvider providerCreateFromMetadata
     */
    public function testCreateFromMetadata(array $data): void
    {
        $defaultData = [
            'keytype' => 'ed11111',
            'scheme' => 'scheme-ed11111',
            'keyval' => new \ArrayObject(['public' => '12345']),
        ];
        $data += $defaultData;
        $key = Key::createFromMetadata(new \ArrayObject($data));
        self::assertInstanceOf(Key::class, $key);
        self::assertSame('ed11111', $key->getType());
        self::assertSame('12345', $key->getPublic());
        $keySortedCanonicalStruct = [
            'keyid_hash_algorithms' => ['sha256', 'sha512'],
            'keytype' => 'ed11111',
            'keyval' => ['public' => '12345'],
            'scheme' => 'scheme-ed11111',
        ];
        $keyCanonicalForm = json_encode($keySortedCanonicalStruct, JSON_UNESCAPED_SLASHES);

        self::assertSame(hash('sha256', $keyCanonicalForm), $key->getComputedKeyId());
    }

    /**
     * Dataprovider for testCreateFromMetadata
     * @return mixed[]
     */
    public function providerCreateFromMetadata(): array
    {
        return [
            'without keyid_hash_algorithms' => [
                [],
            ],
            'with keyid_hash_algorithms' => [
                [
                    'keyid_hash_algorithms' => ["sha256", "sha512"],
                ],
            ]
        ];
    }
}

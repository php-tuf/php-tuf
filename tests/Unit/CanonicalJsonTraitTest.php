<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tuf\CanonicalJsonTrait;

/**
 * @coversDefaultClass \Tuf\CanonicalJsonTrait
 */
class CanonicalJsonTraitTest extends TestCase
{
    use CanonicalJsonTrait;

    /**
     * @covers ::sortKeys
     */
    public function testSort(): void
    {
        $fixturesDirectory = __DIR__ . '/../../fixtures/json';
        $sortedData = json_decode(file_get_contents("$fixturesDirectory/sorted.json"), true, 512, JSON_THROW_ON_ERROR);
        $unsortedData = json_decode(file_get_contents("$fixturesDirectory/unsorted.json"), true, 512, JSON_THROW_ON_ERROR);
        static::sortKeys($unsortedData);
        $this->assertSame($unsortedData, $sortedData);
    }

    /**
     * @covers ::encodeJson
     */
    public function testSlashEscaping(): void
    {
        $json = static::encodeJson(['here/there' => 'everywhere']);
        $this->assertSame('{"here/there":"everywhere"}', $json);
    }
}

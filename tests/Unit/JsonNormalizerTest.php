<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tuf\JsonNormalizer;

/**
 * @coversDefaultClass \Tuf\JsonNormalizer
 */
class JsonNormalizerTest extends TestCase
{

    /**
     * @covers ::rKeySort
     */
    public function testSort(): void
    {
        $fixturesDirectory = __DIR__ . '/../../fixtures/json';
        $sortedData = json_decode(file_get_contents("$fixturesDirectory/sorted.json"), true, 512, JSON_THROW_ON_ERROR);
        $unsortedData = json_decode(file_get_contents("$fixturesDirectory/unsorted.json"), true, 512, JSON_THROW_ON_ERROR);
        JsonNormalizer::rKeySort($unsortedData);
        $this->assertSame($unsortedData, $sortedData);
    }

    /**
     * @covers ::asNormalizedJson
     */
    public function testSlashEscaping(): void
    {
        $json = JsonNormalizer::asNormalizedJson(['here/there' => 'everywhere']);
        $this->assertSame('{"here/there":"everywhere"}', $json);
    }
}

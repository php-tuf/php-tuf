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
     * @covers ::asNormalizedJson
     * @covers ::decode
     *
     * @return void
     */
    public function testSort(): void
    {
        $fixturesDirectory = __DIR__ . '/../../fixtures/json';
        $sortedData = JsonNormalizer::decode(file_get_contents("$fixturesDirectory/sorted.json"));
        $unsortedData = JsonNormalizer::decode(file_get_contents("$fixturesDirectory/unsorted.json"));
        $normalizedFromUnsorted = JsonNormalizer::asNormalizedJson($unsortedData);
        $this->assertSame(JsonNormalizer::asNormalizedJson($sortedData), $normalizedFromUnsorted);
        $this->assertSame(json_encode($sortedData), $normalizedFromUnsorted);
    }

    /**
     * @covers ::asNormalizedJson
     *
     * @return void
     */
    public function testSlashEscaping(): void
    {
        $json = JsonNormalizer::asNormalizedJson(['here/there' => 'everywhere']);
        $this->assertSame('{"here/there":"everywhere"}', $json);
    }
}

<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tuf\JsonNormalizer;

/**
 * @coversDefaultClass \Tuf\JsonNormalizer
 */
class JsonNormalizerTest extends TestCase
{
    public function testSort() {
        $fixtures_directory = __DIR__ . '/../../non_repo_fixtures';
        $sorted_data = JsonNormalizer::decode(file_get_contents("$fixtures_directory/sorted.json"));
        $unsorted_data = JsonNormalizer::decode(file_get_contents("$fixtures_directory/unsorted.json"));
        // asNormalizedJson()
        $this->assertSame(JsonNormalizer::asNormalizedJson($sorted_data), JsonNormalizer::asNormalizedJson($unsorted_data));
        $this->assertSame(json_encode($sorted_data), JsonNormalizer::asNormalizedJson($unsorted_data));
    }
}
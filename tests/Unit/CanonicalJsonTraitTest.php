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

        // Indexed arrays should not be sorted in alphabetical order, at any
        // level.
        $data = [
            // This must have at least 10 items, since that will be sorted
            // alphabetically (which is what we're trying to avoid).
            'b' => array_fill(0, 20, 'Hello!'),
            'a' => 'Canonically speaking, I go before b.',
        ];
        // The associative keys should be in their original, non-canonical
        // order.
        $this->assertSame(['b', 'a'], array_keys($data));
        $this->assertTrue(array_is_list($data['b']));

        static::sortKeys($data);
        // The associative keys should be in canonical order now, and the
        // nested, indexed array should be unchanged.
        $this->assertSame(['a', 'b'], array_keys($data));
        $this->assertTrue(array_is_list($data['b']));
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

<?php


namespace Tuf\Tests\DurableStorage;

use PHPUnit\Framework\TestCase;
use Tuf\Client\DurableStorage\ValidatingArrayAccessAdapter;

class ValidatingArrayAccessAdapterTest extends TestCase
{
    protected function getSystemInTest()
    {
        return new ValidatingArrayAccessAdapter(new InMemoryBackend());
    }

    /**
     * @dataProvider offsetsProvider
     */
    public function testOffsetSet($offset, bool $expectValid)
    {
        $sut = $this->getSystemInTest();
        try {
            $sut[$offset] = "x";
            $actualValid = true;
        } catch (\OutOfBoundsException $e) {
            $actualValid = false;
        }

        $this->assertEquals($expectValid, $actualValid);
    }

    public function offsetsProvider()
    {
        return [
            ['a', true],
            [null, false],
            ['', false],
            ['root.json', true],
            ['dashes-and_underscores', true],
            ['case.5', true],
            ['spaces are asking for trouble', false],
            ['keys/that/../../../../are/paths', false],
        ];
    }
}

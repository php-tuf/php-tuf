<?php


namespace Tuf\Tests\DurableStorage;

use PHPUnit\Framework\TestCase;
use Tuf\Client\DurableStorage\ValidatingArrayAccessAdapter;

/**
 * @coversDefaultClass \Tuf\Client\DurableStorage\ValidatingArrayAccessAdapter
 */
class ValidatingArrayAccessAdapterTest extends TestCase
{
    protected function getSystemInTest()
    {
        return new ValidatingArrayAccessAdapter(new InMemoryBackend());
    }

    /**
     * @covers ::offsetSet
     *
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
            $this->assertEquals($this->getOffSetException($offset), $e->getMessage());
        }

        $this->assertEquals($expectValid, $actualValid);
    }

    /**
     * @covers ::offsetGet
     *
     * @dataProvider offsetsProvider
     */
    public function testOffsetGet($offset, bool $expectValid)
    {
        $sut = $this->getSystemInTest();
        try {
            $value = $sut[$offset];
            $actualValid = true;
        } catch (\OutOfBoundsException $e) {
            $actualValid = false;
            $this->assertEquals($this->getOffSetException($offset), $e->getMessage());
        }

        $this->assertEquals($expectValid, $actualValid);
    }

    /**
     * @covers ::offsetExists
     *
     * @dataProvider offsetsProvider
     */
    public function testOffsetExists($offset, bool $expectValid)
    {
        $sut = $this->getSystemInTest();
        try {
            isset($sut[$offset]);
            $actualValid = true;
        } catch (\OutOfBoundsException $e) {
            $actualValid = false;
            $this->assertEquals($this->getOffSetException($offset), $e->getMessage());
        }

        $this->assertEquals($expectValid, $actualValid);
    }

    /**
     * @covers ::offsetUnset
     *
     * @dataProvider offsetsProvider
     */
    public function testOffsetUnset($offset, bool $expectValid)
    {
        $sut = $this->getSystemInTest();
        try {
            unset($sut[$offset]);
            $actualValid = true;
        } catch (\OutOfBoundsException $e) {
            $actualValid = false;
            $this->assertEquals($this->getOffSetException($offset), $e->getMessage());
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
            ['🔥', false],
        ];
    }

    private function getOffSetException($offset)
    {
        return "Array offset \"$offset\" is not a valid durable storage key.";
    }
}

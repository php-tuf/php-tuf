<?php


namespace Tuf\Tests\DurableStorage;

use PHPUnit\Framework\TestCase;
use Tuf\Client\DurableStorage\DurableStorageAccessValidator;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage;

/**
 * @coversDefaultClass \Tuf\Client\DurableStorage\DurableStorageAccessValidator
 */
class DurableStorageAccessValidatorTest extends TestCase
{

    /**
     * Creates a validated, memory-based storage for the test.
     *
     * @return \ArrayAccess
     *     The test storage.
     */
    protected function getSystemInTest() : \ArrayAccess
    {
        return new DurableStorageAccessValidator(new MemoryStorage());
    }

    /**
     * @covers ::offsetSet
     *
     * @dataProvider offsetsProvider
     *
     * @param mixed $offset
     *     The ArrayAccess offset for the data.
     * @param boolean $expectValid
     *     Whether the value at $offset is expected to be valid.
     *
     * @return void
     */
    public function testOffsetSet($offset, bool $expectValid) : void
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
     *
     * @param mixed $offset
     *     The ArrayAccess offset for the data.
     * @param boolean $expectValid
     *     Whether the value at $offset is expected to be valid.
     *
     * @return void
     */
    public function testOffsetGet($offset, bool $expectValid) : void
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
     *
     * @param mixed $offset
     *     The ArrayAccess offset for the data.
     * @param boolean $expectValid
     *     Whether the value at $offset is expected to be valid.
     *
     * @return void
     */
    public function testOffsetExists($offset, bool $expectValid) : void
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
     *
     * @param mixed $offset
     *     The ArrayAccess offset for the data.
     * @param boolean $expectValid
     *     Whether the value at $offset is expected to be valid.
     *
     * @return void
     */
    public function testOffsetUnset($offset, bool $expectValid) : void
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

    /**
     * Data provider for testOffsetUnset().
     *
     * @return array[]
     *     Test cases. Each test contains:
     *     - The test case \ArrayAccess offset.
     *     - Whether the value is expected to be valid.
     */
    public function offsetsProvider() : array
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

    /**
     * Gets the expected exception message if an offset is invalid.
     *
     * @param mixed $offset
     *     The \ArrayAccess offset.
     *
     * @return string
     *     The expected exception message.
     */
    private function getOffSetException($offset) : string
    {
        return "Array offset '$offset' is not a valid durable storage key.";
    }
}

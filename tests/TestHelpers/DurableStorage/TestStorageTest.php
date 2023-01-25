<?php

namespace Tuf\Tests\TestHelpers\DurableStorage;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Tuf\Tests\TestHelpers\DurableStorage\TestStorage
 */
class TestStorageTest extends TestCase
{

    /**
     * @covers ::setExceptionOnChange
     *
     * @return void
     */
    public function testSetExceptionOnChange(): void
    {
        $storage = new TestStorage();
        $storage->write('test_key', 'value');
        $storage->setExceptionOnChange();
        self::assertSame('value', $storage->read('test_key'));
        try {
            $storage->write('test_key', 'value');
            $this->fail('No exception on set');
        } catch (\LogicException $logicException) {
            // Assert no change was made.
            self::assertSame('value', $storage->read('test_key'));
            $this->expectException(\LogicException::class);
            $storage->delete('test_key');
        }
    }
}

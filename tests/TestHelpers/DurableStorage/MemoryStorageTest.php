<?php

namespace Tuf\Tests\TestHelpers\DurableStorage;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage
 */
class MemoryStorageTest extends TestCase
{

    /**
     * @covers ::setExceptionOnChange
     *
     * @return void
     */
    public function testSetExceptionOnChange(): void
    {
        $storage = new MemoryStorage();
        $storage->offsetSet('test_key', 'value');
        $storage->setExceptionOnChange();
        self::assertTrue($storage->offsetExists('test_key'));
        self::assertSame('value', $storage->offsetGet('test_key'));
        try {
            $storage->offsetSet('test_key', 'value');
            $this->fail('No exception on set');
        } catch (\LogicException $logicException) {
            // Assert no change was made.
            self::assertSame('value', $storage->offsetGet('test_key'));
            $this->expectException(\LogicException::class);
            $storage->offsetUnset('test_key');
        }
    }
}

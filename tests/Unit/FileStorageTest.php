<?php

namespace Tuf\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tuf\Client\DurableStorage\FileStorage;

/**
 * @covers \Tuf\Client\DurableStorage\FileStorage
 */
class FileStorageTest extends TestCase
{
    /**
     * Tests creating a FileStorage object with an invalid directory.
     *
     * @return void
     */
    public function testCreateWithInvalidDirectory(): void
    {
        $dir = '/nonsensedirdoesnotexist' . uniqid();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage("Cannot initialize filesystem local state: '$dir' is not a directory.");
        new FileStorage($dir);
    }

    /**
     * Tests creating and interacting with files via a FileStorage object.
     *
     * @return void
     */
    public function testStorage(): void
    {
        $dir = sys_get_temp_dir();
        $storage = new FileStorage($dir);
        $filename = uniqid();
        $this->assertFalse(isset($storage[$filename]));
        $storage[$filename] = "From hell's heart, I refactor thee!";
        $this->assertFileExists("$dir/$filename");
        $this->assertTrue(isset($storage[$filename]));
        $this->assertSame("From hell's heart, I refactor thee!", $storage[$filename]);
        unset($storage[$filename]);
        $this->assertFileDoesNotExist("$dir/$filename");
    }
}

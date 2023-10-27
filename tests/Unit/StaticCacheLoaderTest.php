<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\StaticCacheLoader;

/**
 * @covers \Tuf\Loader\StaticCacheLoader
 */
class StaticCacheLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function testStaticCacheLoader(): void
    {
        $decorated = $this->prophesize(LoaderInterface::class);
        $stream = Utils::streamFor('Row, row, row your boat...');

        $locator = 'uno.txt';
        $maxBytes = 59;
        $decorated->load($locator, $maxBytes)
            ->willReturn($stream)
            ->shouldBeCalledTimes(2);

        $loader = new StaticCacheLoader($decorated->reveal());
        $this->assertSame($stream, $loader->load($locator, $maxBytes));
        $this->assertSame($stream, $loader->load($locator, $maxBytes));
        $loader->reset();
        $this->assertSame($stream, $loader->load($locator, $maxBytes));
    }
}

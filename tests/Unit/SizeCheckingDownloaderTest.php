<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Tuf\Downloader\SizeCheckingDownloader;
use Tuf\Exception\DownloadSizeException;
use Tuf\Tests\TestHelpers\TestDownloader;

/**
 * @covers \Tuf\Downloader\SizeCheckingDownloader
 */
class SizeCheckingDownloaderTest extends TestCase
{
    public function provider(): array
    {
        return [
            ['Deep Space 9 is the best Star Trek series. Come at me, bro.'],
            ['short'],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function testStreamSizeIsKnown(string $contents): void
    {
        $stream = Utils::streamFor($contents);
        $this->assertStreamSizeIsChecked($stream->getSize(), $stream);

        // If the stream isn't long enough to raise an exception, this test
        // won't assert anything and PHPUnit will complain.
        $this->assertTrue(true);
    }

    /**
     * @dataProvider provider
     */
    public function testStreamSizeIsNotKnown(string $contents): void
    {
        $buffer = Utils::tryFopen('php://temporary', 'a+');
        $bytesWritten = fwrite($buffer, $contents);
        $this->assertNotFalse($bytesWritten);

        $this->assertStreamSizeIsChecked($bytesWritten, new class ($buffer) extends Stream {

            public function getSize()
            {
                return null;
            }

        });
    }

    private function assertStreamSizeIsChecked(int $actualLength, StreamInterface $stream): void
    {
        $decorated = new TestDownloader();
        $decorated->set('test.txt', $stream);

        $downloader = new SizeCheckingDownloader($decorated);
        if ($actualLength > 8) {
            $this->expectException(DownloadSizeException::class);
            $this->expectExceptionMessage("test.txt exceeded 8 bytes.");
        }
        $downloader->download('test.txt', 8)->wait();
    }
}

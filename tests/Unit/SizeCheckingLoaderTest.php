<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\SizeCheckingLoader;

/**
 * @covers \Tuf\Loader\SizeCheckingLoader
 */
class SizeCheckingLoaderTest extends TestCase implements LoaderInterface
{
    private StreamInterface $stream;

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): StreamInterface
    {
        return $this->stream;
    }

    public function testStreamSizeIsChecked(): void
    {
        $loader = new SizeCheckingLoader($this);

        // If we don't pass $maxBytes, it doesn't matter how big the stream is.
        $this->stream = Utils::streamFor('Deep Space Nine is the best Star Trek series. This is a scientific fact.');
        $loader->load('ok.txt');

        // If $maxBytes is bigger than the size of the stream, we shouldn't have
        // a problem.
        $loader->load('ok.txt', 1024);

        // If the size of the stream is known, we should get an error if it's
        // longer than $maxBytes.
        $this->assertGreaterThan(0, $this->stream->getSize());
        try {
            $loader->load('too_long_known_size.txt', 8);
            $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
        } catch (DownloadSizeException $e) {
            $this->assertSame('too_long_known_size.txt exceeded 8 bytes', $e->getMessage());
        }

        // Make a new stream that doesn't know how long it is, and ensure we
        // still get an error if it's longer than $maxBytes.
        $buffer = fopen('php://memory', 'a+');
        $this->assertIsResource($buffer);
        // Transfer the contents of the old stream into the new buffer.
        $bytesWritten = fwrite($buffer, $this->stream->getContents());
        $this->assertGreaterThan(0, $bytesWritten);
        $this->stream = new class ($buffer) extends Stream {

            public function getSize()
            {
                return null;
            }

        };
        try {
            $loader->load('too_long_unknown_size.txt', 8);
            $this->fail('Expected DownloadSizeException to be thrown, but it was not.');
        } catch (DownloadSizeException $e) {
            $this->assertSame('too_long_unknown_size.txt exceeded 8 bytes', $e->getMessage());
        }
    }
}

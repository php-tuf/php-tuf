<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Tuf\Downloader\GuzzleDownloader;
use Tuf\Exception\RepoFileNotFound;

/**
 * @covers \Tuf\Downloader\GuzzleDownloader
 */
class GuzzleDownloaderTest extends TestCase
{
    public function testRequestOptionsArePassed(): void
    {
        $mockHandler = new MockHandler();
        $history = [];
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($history));
        $client = new Client(['handler' => $handlerStack]);
        $downloader = new GuzzleDownloader($client);

        // If $maxBytes is passed, there should be progress callback in the
        // request options.
        $mockHandler->append(new Response());
        $this->assertInstanceOf(StreamInterface::class, $downloader->download('test.txt', 10)->wait());
        $requestOptions = $history[0]['options'];
        $this->assertTrue($requestOptions[RequestOptions::STREAM]);
        $this->assertIsCallable($requestOptions[RequestOptions::PROGRESS]);

        // If $maxBytes isn't passed, there should be no progress callback.
        $mockHandler->append(new Response());
        $this->assertInstanceOf(StreamInterface::class, $downloader->download('test.txt')->wait());
        $requestOptions = $history[1]['options'];
        $this->assertTrue($requestOptions[RequestOptions::STREAM]);
        $this->assertArrayNotHasKey(RequestOptions::PROGRESS, $requestOptions);

        // A 404 should raise a RepoFileNotFound exception.
        $mockHandler->append(new Response(404));
        try {
            $downloader->download('test.txt')->wait();
            $this->fail('Expected an exception to be thrown.');
        } catch (RepoFileNotFound $e) {
            $this->assertSame("test.txt not found.", $e->getMessage());
        }

        // A 500 error should be wrapped and re-thrown as a runtime exception.
        $mockHandler->append(new Response(500));
        try {
            $downloader->download('test.txt')->wait();
            $this->fail('Expected an exception to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertInstanceOf(ServerException::class, $e->getPrevious());
        }
    }
}

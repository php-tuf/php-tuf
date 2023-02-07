<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\GuzzleLoader;

/**
 * @covers \Tuf\Loader\GuzzleLoader
 */
class GuzzleLoaderTest extends TestCase
{
    public function testGuzzleLoader(): void
    {
        $history = [];
        $handler = new MockHandler();
        $handlerStack = HandlerStack::create($handler);
        $handlerStack->push(Middleware::history($history));
        $client = new Client(['handler' => $handlerStack]);

        $loader = new GuzzleLoader($client);

        // Ensure that we always ask Guzzle to stream from the server, with
        // a progress callback to check the downloaded size in transit.
        $handler->append(new Response());
        $loader->load('test.txt', 1024);
        $this->assertTrue($history[0]['options'][RequestOptions::STREAM]);
        $this->assertIsCallable($history[0]['options'][RequestOptions::PROGRESS]);

        // A 404 should result in a RepoNotFound exception wrapping the original
        // ClientException.
        $handler->append(new Response(404));
        try {
            $loader->load('vapor.txt', 1024);
            $this->fail('Expected a RepoFileNotFound exception, but none was thrown.');
        } catch (RepoFileNotFound $e) {
            $this->assertSame('vapor.txt not found', $e->getMessage());
            $this->assertSame(404, $e->getCode());
            $this->assertInstanceOf(ClientException::class, $e->getPrevious());
        }

        // Any other 400 response should be wrapped and re-thrown.
        $handler->append(new Response(400));
        try {
            $loader->load('error.txt', 1024);
            $this->fail('Expected a ClientException, but none was thrown.');
        } catch (ClientException $e) {
            $this->assertInstanceOf(ClientException::class, $e->getPrevious());
        }

        // A 5xx error should not be caught at all.
        $handler->append(new Response(500));
        $this->expectException('\GuzzleHttp\Exception\ServerException');
        $loader->load('wat.txt', 1024);
    }
}

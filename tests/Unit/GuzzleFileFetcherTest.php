<?php


namespace Tuf\Tests\Unit;


use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tuf\Client\GuzzleFileFetcher;
use Tuf\Exception\RepoFileNotFound;

class GuzzleFileFetcherTest extends TestCase
{
    public function errorProvider() : array
    {
        return [
          [404, RepoFileNotFound::class],
          [403, 'RuntimeException'],
          [500, 'RuntimeException'],
        ];
    }

    /**
     * @param int $statusCode
     * @param string $exceptionClass
     *
     * @dataProvider errorProvider
     */
    public function testError(int $statusCode, string $exceptionClass) : void
    {
        $mockHandler = new MockHandler();
        $mockHandler->append(new Response($statusCode));
        $handlerStack = HandlerStack::create($mockHandler);
        $middleware = Middleware::httpErrors();
        $handlerStack->push($middleware);
        $client = new Client(['handler' => $handlerStack]);

        $fetcher = new GuzzleFileFetcher($client);
        $this->expectException($exceptionClass);
        $fetcher->fetchFile('test.json', 1024);
    }
}

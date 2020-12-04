<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tuf\Client\GuzzleFileFetcher;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\RepoFileNotFound;

/**
 * @coversDefaultClass \Tuf\Client\GuzzleFileFetcher
 */
class GuzzleFileFetcherTest extends TestCase
{
    /**
     * The mocked request handler.
     *
     * @var \GuzzleHttp\Handler\MockHandler
     */
    private $mockHandler;

    /**
     * The mocked HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * The JSON content of the mocked response(s).
     *
     * @var string
     */
    private $testContent = '["oolong","assam","matcha","herbal"]';

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHandler = new MockHandler();

        $handlerStack = HandlerStack::create($this->mockHandler);
        // The httpErrors() middleware will throw an exception if the status
        // code is not 200.
        $handlerStack->push(Middleware::httpErrors());
        $this->client = new Client(['handler' => $handlerStack]);
    }

    /**
     * Data provider for testfetchFileError().
     *
     * @return array[]
     *   Sets of arguments to pass to the test method.
     */
    public function providerFetchFileError(): array
    {
        return [
            [404, RepoFileNotFound::class, 0],
            [403, 'RuntimeException'],
            [500, BadResponseException::class],
            [200, DownloadSizeException::class, 0, 4],
        ];
    }

    /**
     * Data provider for testFetchFileIfExistsError().
     *
     * @return array[]
     *   Sets of arguments to pass to the test method.
     */
    public function providerFileIfExistsError(): array
    {
        return [
            [403, 'RuntimeException'],
            [500, BadResponseException::class],
            [200, DownloadSizeException::class, 0, 4],
        ];
    }

    /**
     * Tests various error conditions when fetching a file with fetchFile().
     *
     * @param integer $statusCode
     *   The response status code.
     * @param string $exceptionClass
     *   The expected exception class that will be thrown.
     * @param integer|null $exceptionCode
     *   (optional) The expected exception code. Defaults to the status code.
     * @param integer|null $maxBytes
     *   (optional) The maximum number of bytes to read from the response.
     *   Defaults to the length of $this->testContent.
     *
     * @return void
     *
     * @dataProvider providerFetchFileError
     *
     * @covers ::fetchFile
     */
    public function testFetchFileError(int $statusCode, string $exceptionClass, ?int $exceptionCode = null, ?int $maxBytes = null): void
    {
        $this->mockHandler->append(new Response($statusCode, [], $this->testContent));
        $this->expectException($exceptionClass);
        $this->expectExceptionCode($exceptionCode ?? $statusCode);
        $fetcher = new GuzzleFileFetcher($this->client);
        $fetcher->fetchFile('test.json', $maxBytes ?? strlen($this->testContent));
    }

    /**
     * Tests various error conditions when fetching a file with fetchFileIfExists().
     *
     * @param integer $statusCode
     *   The response status code.
     * @param string $exceptionClass
     *   The expected exception class that will be thrown.
     * @param integer|null $exceptionCode
     *   (optional) The expected exception code. Defaults to the status code.
     * @param integer|null $maxBytes
     *   (optional) The maximum number of bytes to read from the response.
     *   Defaults to the length of $this->testContent.
     *
     * @return void
     *
     * @dataProvider providerFileIfExistsError
     *
     * @covers ::providerFileIfExists
     */
    public function testFetchFileIfExistsError(int $statusCode, string $exceptionClass, ?int $exceptionCode = null, ?int $maxBytes = null): void
    {
        $this->mockHandler->append(new Response($statusCode, [], $this->testContent));
        $this->expectException($exceptionClass);
        $this->expectExceptionCode($exceptionCode ?? $statusCode);
        $fetcher = new GuzzleFileFetcher($this->client);
        $fetcher->fetchFileIfExists('test.json', $maxBytes ?? strlen($this->testContent));
    }

    /**
     * Tests fetching a file without any errors.
     *
     * @return void
     */
    public function testSuccessfulFetch(): void
    {
        $fetcher = new GuzzleFileFetcher($this->client);
        $this->mockHandler->append(new Response(200, [], $this->testContent));
        $this->assertSame($fetcher->fetchFile('test.json', 256), $this->testContent);
        $this->mockHandler->append(new Response(200, [], $this->testContent));
        $this->assertSame($fetcher->fetchFileIfExists('test.json', 256), $this->testContent);
        $this->mockHandler->append(new Response(404, []));
        $this->assertNull($fetcher->fetchFileIfExists('test.json', 256));
    }

    /**
     * Tests creating a file fetcher with a repo base URI.
     *
     * @return void
     *
     * @covers ::createFromUri
     */
    public function testCreateFromUri(): void
    {
        $this->assertInstanceOf(GuzzleFileFetcher::class, GuzzleFileFetcher::createFromUri('https://example.com'));

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Repo base URI must be HTTPS: http://example.com');
        GuzzleFileFetcher::createFromUri('http://example.com');
    }
}

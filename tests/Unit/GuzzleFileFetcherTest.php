<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Client\GuzzleFileFetcher;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\GuzzleLoader;
use Tuf\Loader\LoaderInterface;

/**
 * @coversDefaultClass \Tuf\Client\GuzzleFileFetcher
 */
class GuzzleFileFetcherTest extends TestCase
{
    use ProphecyTrait;

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
     * The content of the mocked response(s).
     *
     * This is deliberately not readable by json_decode(), in order to prove
     * that the fetcher does not try to parse or process the response content
     * in any way.
     *
     * @var string
     */
    private $testContent = 'Zombie ipsum reversus ab viral inferno, nam rick grimes malum cerebro.';

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
     * Returns an instance of the file fetcher under test.
     *
     * @return \Tuf\Client\GuzzleFileFetcher
     *   An instance of the file fetcher under test.
     */
    private function getFetcher(): GuzzleFileFetcher
    {
        $metadataLoader = new GuzzleLoader($this->client);
        return new GuzzleFileFetcher($metadataLoader);
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
            [500, 'RuntimeException'],
        ];
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
     * @covers ::fetchMetadataIfExists
     */
    public function testFetchFileIfExistsError(int $statusCode, string $exceptionClass, ?int $exceptionCode = null, ?int $maxBytes = null): void
    {
        $this->mockHandler->append(new Response($statusCode, [], $this->testContent));
        try {
            $metadata = $this->getFetcher()->fetchMetadataIfExists('test.json', $maxBytes ?? strlen($this->testContent));
            // If we don't get an exception, ensure we expected a
            // RepoFileNotFound, which should be converted to a harmless null
            // by fetchMetadataIfExists().
            $this->assertSame(RepoFileNotFound::class, $exceptionClass);
            $this->assertNull($metadata);
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertSame($exceptionCode ?? $statusCode, $e->getCode());
        }
    }

    /**
     * Tests fetching a file without any errors.
     *
     * @return void
     */
    public function testSuccessfulFetch(): void
    {
        $fetcher = $this->getFetcher();
        $this->mockHandler->append(new Response(200, [], $this->testContent));
        $this->assertSame($fetcher->fetchMetadataIfExists('test.json', 256), $this->testContent);
        $this->mockHandler->append(new Response(404, []));
        $this->assertNull($fetcher->fetchMetadataIfExists('test.json', 256));
    }

    /**
     * Tests that prefixes for metadata and targets are respected.
     *
     * @return void
     */
    public function testPrefixes(): void
    {
        $promise = new FulfilledPromise(Utils::streamFor(''));

        $metadataLoader = $this->prophesize(LoaderInterface::class);
        $metadataLoader->load('root.json', 128)
            ->willReturn($promise)
            ->shouldBeCalled();

        $fetcher = new GuzzleFileFetcher($metadataLoader->reveal());
        $fetcher->fetchMetadataIfExists('root.json', 128);
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
    }
}

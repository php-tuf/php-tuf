<?php

namespace Tuf\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Tuf\Exception\DownloadSizeException;
use Tuf\Exception\MetadataException;
use Tuf\Exception\RepoFileNotFound;
use Tuf\GuzzleRepository;
use Tuf\Metadata\RootMetadata;
use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TargetsMetadata;
use Tuf\Metadata\TimestampMetadata;

/**
 * @covers \Tuf\GuzzleRepository
 */
class GuzzleRepositoryTest extends TestCase
{
    use ProphecyTrait;

    private MockHandler $mockHandler;

    private GuzzleRepository $repository;

    private array $history = [];

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);

        // Ensure that we can inspect the request and response history.
        $history = Middleware::history($this->history);
        $handlerStack->push($history);

        $client = new Client(['handler' => $handlerStack]);
        $this->repository = new GuzzleRepository($client);
    }

    public function providerFetchMetadata(): array
    {
        return [
            'root' => [
                '1.root.json',
                'getRoot',
                [1],
                RootMetadata::class,
            ],
            'timestamp' => [
                'timestamp.json',
                'getTimestamp',
                [],
                TimestampMetadata::class,
            ],
            'snapshot, without version' => [
                'snapshot.json',
                'getSnapshot',
                [null],
                SnapshotMetadata::class,
            ],
            'snapshot, with version' => [
                '1.snapshot.json',
                'getSnapshot',
                [1],
                SnapshotMetadata::class,
            ],
            'targets, without version' => [
                'targets.json',
                'getTargets',
                [null],
                TargetsMetadata::class,
            ],
            'targets, with version' => [
                '1.targets.json',
                'getTargets',
                [1],
                TargetsMetadata::class,
            ],
            'delegated role, without version' => [
                'unclaimed.json',
                'getTargets',
                [null, 'unclaimed'],
                TargetsMetadata::class,
            ],
            'delegated role, with version' => [
                '1.unclaimed.json',
                'getTargets',
                [1, 'unclaimed'],
                TargetsMetadata::class,
            ],
        ];
    }

    /**
     * @dataProvider providerFetchMetadata
     */
    public function testFetchMetadata(string $fileName, string $method, array $arguments, string $metadataClass): void
    {
        $file = fopen(__DIR__ . "/../../fixtures/Delegated/consistent/server/metadata/$fileName", 'r');
        $this->assertIsResource($file);
        $this->mockHandler->append(new Response(200, [], $file));

        $metadata = $this->repository->$method(...$arguments)->wait();
        $this->assertInstanceOf($metadataClass, $metadata);
        // Ensure that the correct file was requested.
        $this->assertSame($fileName, $this->history[0]['request']->getUri()->getPath());

        // If we were fetching targets metadata, ensure the metadata object we
        // got back has the correct role.
        if ($metadata instanceof TargetsMetadata) {
            $this->assertSame($arguments[1] ?? 'targets', $metadata->getRole());
        }

        // If the response is a 404, we should get an exception for everything
        // except the root metadata, which should merely return null.
        $this->mockHandler->append(new Response(404));
        if ($metadataClass !== RootMetadata::class) {
            $this->expectException(RepoFileNotFound::class);
            $this->expectExceptionMessage("$fileName not found");
        }
        $this->assertNull($this->repository->$method(...$arguments)->wait());
    }

    /**
     * Data provider for testing methods that behave the same way.
     *
     * @return array[]
     *   The test cases.
     */
    public function providerStandardInvocations(): array
    {
        return [
            'root' => [
                'getRoot',
                [1],
            ],
            'timestamp' => [
                'getTimestamp',
                [],
            ],
            'snapshot with version' => [
                'getSnapshot',
                [1],
            ],
            'snapshot without version' => [
                'getSnapshot',
                [null],
            ],
            'targets with version' => [
                'getTargets',
                [1],
            ],
            'targets without version' => [
                'getTargets',
                [null],
            ],
            'delegated role with version' => [
                'getTargets',
                [1, 'delegated'],
            ],
            'delegated role without version' => [
                'getTargets',
                [null, 'delegated'],
            ],
        ];
    }

    /**
     * @dataProvider providerStandardInvocations
     */
    public function testServerError(string $method, array $arguments): void
    {
        $this->mockHandler->append(new Response(500));
        // If there's an internal server error, Guzzle should throw a
        // ServerException, which we should wrap in a RuntimException and
        // re-throw.
        $this->expectException('RuntimeException');
        $this->expectExceptionCode(500);
        $this->repository->$method(...$arguments)->wait();
    }

    /**
     * @dataProvider providerStandardInvocations
     */
    public function testInvalidJson(string $method, array $arguments): void
    {
        $this->mockHandler->append(new Response(200, [], '{"invalid": "data"}'));
        // If createFromJson() cannot validate the JSON returned by the server,
        // we should get a MetadataException right away.
        $this->expectException(MetadataException::class);
        $this->repository->$method(...$arguments)->wait();
    }

    public function providerSizeLimit(): array
    {
        return [
            'root' => [
                'getRoot',
                [1],
                '1.root.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'timestamp' => [
                'getTimestamp',
                [],
                'timestamp.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'versioned snapshot' => [
                'getSnapshot',
                [1],
                '1.snapshot.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'un-versioned snapshot' => [
                'getSnapshot',
                [null],
                'snapshot.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'versioned snapshot with explicit size' => [
                'getSnapshot',
                [1, 128],
                '1.snapshot.json',
                128,
            ],
            'un-versioned snapshot with explicit size' => [
                'getSnapshot',
                [null, 128],
                'snapshot.json',
                128,
            ],
            'versioned targets' => [
                'getTargets',
                [1],
                '1.targets.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'un-versioned targets' => [
                'getTargets',
                [null],
                'targets.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'versioned targets with explicit size' => [
                'getTargets',
                [1, 'targets', 128],
                '1.targets.json',
                128,
            ],
            'un-versioned targets with explicit size' => [
                'getTargets',
                [null, 'targets', 128],
                'targets.json',
                128,
            ],
            'versioned delegated role' => [
                'getTargets',
                [1, 'delegated'],
                '1.delegated.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'un-versioned delegated role' => [
                'getTargets',
                [null, 'delegated'],
                'delegated.json',
                GuzzleRepository::MAXIMUM_BYTES,
            ],
            'versioned delegated role with explicit size' => [
                'getTargets',
                [1, 'delegated', 128],
                '1.delegated.json',
                128,
            ],
            'un-versioned delegated role with explicit size' => [
                'getTargets',
                [null, 'delegated', 128],
                'delegated.json',
                128,
            ],
        ];
    }

    /**
     * @dataProvider providerSizeLimit
     */
    public function testSizeLimit(string $method, array $arguments, string $fileName, int $maxSize): void
    {
        // Create a buffer in memory with more than the maximum allowed number
        // of bytes written to it.
        $buffer = fopen('php://memory', 'a+');
        $this->assertIsResource($buffer);
        $bytesWritten = fwrite($buffer, str_repeat('-', $maxSize + 1));
        $this->assertGreaterThan($maxSize, $bytesWritten);

        // Wrap that buffer in a stream which will report whatever size we
        // tell it to.
        $body = new class ($buffer) extends Stream {

            public ?int $size;

            public function getSize()
            {
                return $this->size;
            }

        };

        // Ensure we test what happens when the stream does and doesn't know
        // how long it is.
        foreach ([null, $bytesWritten] as $reportedSize) {
            $body->size = $reportedSize;

            $this->mockHandler->append(new Response(200, [], $body));
            try {
                $this->repository->$method(...$arguments)->wait();
                $this->fail('Expected a DownloadSizeException to be thrown.');
            } catch (DownloadSizeException $e) {
                $this->assertSame("$fileName exceeded $maxSize bytes", $e->getMessage());
            }
        }
    }
}

<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils;
use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\JsonNormalizer;
use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * Defines an implementation of RepoFileFetcherInterface to use with test fixtures.
 */
class TestRepo implements RepoFileFetcherInterface
{
    use UtilsTrait;

    /**
     * An array of repo file contents keyed by file name.
     *
     * @var string[]
     */
    public $repoFilesContents = [];

    /**
     * TestRepo constructor.
     *
     * @param string $fixturesSet
     *   The fixtures set to use.
     */
    public function __construct(string $fixturesSet)
    {
        // Store all the repo files locally so they can be easily altered.
        // @see self::setRepoFileNestedValue()
        $repoFiles = glob(static::getFixturesRealPath($fixturesSet, '/tufrepo/metadata') . '/*.json');
        foreach ($repoFiles as $repoFile) {
            $this->repoFilesContents[basename($repoFile)] = file_get_contents($repoFile);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function fetchMetadata(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->fetchFile($fileName);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchTarget(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->fetchFile($fileName);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchFile(string $fileName): PromiseInterface
    {
        if (empty($this->repoFilesContents[$fileName])) {
            return new RejectedPromise(new RepoFileNotFound("File $fileName not found."));
        }
        // Allow test code to directly set the returned promise so that the
        // underlying streams can be mocked.
        $contents = $this->repoFilesContents[$fileName];
        if ($contents instanceof PromiseInterface) {
            return $contents;
        }
        $stream = Utils::streamFor($this->repoFilesContents[$fileName]);
        return new FulfilledPromise($stream);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchMetadataIfExists(string $fileName, int $maxBytes):?string
    {
        try {
            return $this->fetchFile($fileName, $maxBytes)->wait();
        } catch (RepoFileNotFound $exception) {
            return null;
        }
    }

    /**
     * Sets a nested value in a repo file.
     *
     * @param string $fileName
     *   The name of the file to change.
     * @param array $keys
     *   The nested array keys of the item.
     * @param mixed $newValue
     *   The new value to set.
     *
     * @return void
     */
    public function setRepoFileNestedValue(string $fileName, array $keys, $newValue): void
    {
        $json = json_decode($this->repoFilesContents[$fileName], true);
        static::nestedChange($keys, $json, $newValue);
        $this->repoFilesContents[$fileName] = JsonNormalizer::asNormalizedJson($json);
    }

    /**
     * Removes a file from the repo.
     *
     * @param string $fileName
     *   The name of the file to remove.
     *
     * @return void
     */
    public function removeRepoFile(string $fileName):void
    {
        unset($this->repoFilesContents[$fileName]);
    }
}

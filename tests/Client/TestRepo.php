<?php

namespace Tuf\Tests\Client;

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
    private $repoFilesContents = [];

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
     * {@inheritdoc}
     */
    public function fetchFile(string $fileName, int $maxBytes)
    {
        try {
            if (empty($this->repoFilesContents[$fileName])) {
                throw new RepoFileNotFound("File $fileName not found.");
            }
            return $this->repoFilesContents[$fileName];
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Sets a nested value in a repo file.
     *
     * @param string $fileName
     *   The file name to change.
     * @param array $keys
     *   The keys of the nested item.
     * @param mixed $newValue
     *   The new value.
     *
     * @return void
     */
    public function setRepoFileNestedValue(string $fileName, array $keys = ['signed', 'extra_test_value'], $newValue = 'new value')
    {
        $json = json_decode($this->repoFilesContents[$fileName], true);
        static::nestedChange($keys, $json, $newValue);
        $this->repoFilesContents[$fileName] = JsonNormalizer::asNormalizedJson($json);
    }
}

<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\CanonicalJsonTrait;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\LoaderInterface;
use Tuf\Tests\TestHelpers\UtilsTrait;

class TestLoader implements LoaderInterface
{
    use CanonicalJsonTrait;
    use UtilsTrait;

    /**
     * An array of repo file contents keyed by file name.
     *
     * @var string[]
     */
    public $fileContents = [];

    /**
     * The $maxBytes argument passed to ::load() each time it was called.
     *
     * This is used by tests to confirm that the updater passes the expected
     * file names and maximum download lengths.
     *
     * @var array[]
     */
    public array $maxBytes = [];

    /**
     * TestRepo constructor.
     *
     * @param string $basePath
     *   The path of the fixture to read from.
     */
    public function __construct(string $basePath)
    {
        // Store all the repo files locally so they can be easily altered.
        // @see self::setRepoFileNestedValue()
        $fixturesPath = "$basePath/server";
        $repoFiles = glob("$fixturesPath/metadata/*.json");
        $targetsPath = "$fixturesPath/targets";
        if (is_dir($targetsPath)) {
            $repoFiles = array_merge($repoFiles, glob("$targetsPath/*"));
        }
        foreach ($repoFiles as $repoFile) {
            $baseName = basename($repoFile);
            if (isset($this->fileContents[$baseName])) {
                throw new \UnexpectedValueException("For testing fixtures target files should not use metadata file names");
            }
            $this->fileContents[$baseName] = file_get_contents($repoFile);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $locator, int $maxBytes): StreamInterface
    {
        $this->maxBytes[$locator][] = $maxBytes;

        if (empty($this->fileContents[$locator])) {
            throw new RepoFileNotFound("File $locator not found.");
        }
        // Allow test code to directly set the returned stream so that they can
        // be mocked.
        $contents = $this->fileContents[$locator];
        if ($contents instanceof StreamInterface) {
            return $contents;
        }
        return Utils::streamFor($this->fileContents[$locator]);
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
        $json = json_decode($this->fileContents[$fileName], true);
        static::nestedChange($keys, $json, $newValue);
        $this->fileContents[$fileName] = static::encodeJson($json);
    }

    /**
     * Removes a file from the repo.
     *
     * @param string $fileName
     *   The name of the file to remove.
     *
     * @return void
     */
    public function removeRepoFile(string $fileName): void
    {
        unset($this->fileContents[$fileName]);
    }
}

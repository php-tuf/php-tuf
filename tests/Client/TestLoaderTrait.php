<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\RepoFileNotFound;

trait TestLoaderTrait
{
    /**
     * An array of repo file contents keyed by file name.
     *
     * @var string[]
     */
    protected array $fileContents = [];

    /**
     * The $maxBytes argument passed to ::load() each time it was called.
     *
     * This is used by tests to confirm that the updater passes the expected
     * file names and maximum download lengths.
     *
     * @var array[]
     */
    protected array $maxBytes = [];

    /**
     * TestRepo constructor.
     *
     * @param string $basePath
     *   The path of the fixture to read from.
     */
    public function populateFromFixture(string $basePath): void
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

        if (array_key_exists($locator, $this->fileContents)) {
            // Allow test code to directly set the returned stream so that they
            // can be mocked.
            return Utils::streamFor($this->fileContents[$locator]);
        } else {
            throw new RepoFileNotFound("File $locator not found.");
        }
    }
}

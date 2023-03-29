<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\RepoFileNotFound;

/**
 * Defines a trait to load files from a simulated, in-memory server.
 *
 * Classes using this trait should implement \Tuf\Loader\LoaderInterface.
 */
trait TestLoaderTrait
{
    /**
     * An array of file contents keyed by file name.
     *
     * These can either be the plain file contents as strings, or stream objects
     * that should be returned as-is.
     *
     * @var string[]|\Psr\Http\Message\StreamInterface[]
     *
     * @see ::load()
     */
    protected array $fileContents = [];

    /**
     * The $maxBytes arguments passed to ::load(), keyed by file name.
     *
     * This is used to confirm that the updater passes the expected file names
     * and maximum download lengths.
     *
     * @var int[][]
     */
    protected array $maxBytes = [];

    /**
     * Populates $this->fileContents with a fixture's server-side metadata.
     *
     * @param string $basePath
     *   The path of the fixture to read from.
     */
    public function populateFromFixture(string $basePath): void
    {
        // Store the file contents in memory so they can be easily altered.
        $fixturesPath = "$basePath/server";
        $files = glob("$fixturesPath/metadata/*.json");
        $targetsPath = "$fixturesPath/targets";
        if (is_dir($targetsPath)) {
            $files = array_merge($files, glob("$targetsPath/*"));
        }
        foreach ($files as $file) {
            $baseName = basename($file);
            if (array_key_exists($baseName, $this->fileContents)) {
                throw new \UnexpectedValueException("For testing fixtures target files should not use metadata file names");
            }
            $this->fileContents[$baseName] = file_get_contents($file);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $locator, int $maxBytes): StreamInterface
    {
        $this->maxBytes[$locator][] = $maxBytes;

        if (array_key_exists($locator, $this->fileContents)) {
            return Utils::streamFor($this->fileContents[$locator]);
        } else {
            throw new RepoFileNotFound("File $locator not found.");
        }
    }
}

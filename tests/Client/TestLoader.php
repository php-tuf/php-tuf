<?php

namespace Tuf\Tests\Client;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\LoaderInterface;

/**
 * Defines a trait to load files from a simulated, in-memory server.
 *
 * Classes using this trait should implement \Tuf\Loader\LoaderInterface.
 */
class TestLoader extends \ArrayObject implements LoaderInterface
{
    /**
     * The $maxBytes arguments passed to ::load(), keyed by file name.
     *
     * This is used to confirm that the updater passes the expected file names
     * and maximum download lengths.
     *
     * @var int[][]
     */
    public array $maxBytes = [];

    /**
     * Populates this object with a fixture's server-side metadata.
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
            if ($this->offsetExists($baseName)) {
                throw new \UnexpectedValueException("For testing fixtures target files should not use metadata file names");
            }
            $this[$baseName] = file_get_contents($file);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $locator, int $maxBytes): StreamInterface
    {
        $this->maxBytes[$locator][] = $maxBytes;

        if ($this->offsetExists($locator)) {
            return Utils::streamFor($this[$locator]);
        } else {
            throw new RepoFileNotFound("File $locator not found.");
        }
    }
}

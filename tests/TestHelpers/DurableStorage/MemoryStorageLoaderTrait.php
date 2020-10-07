<?php


namespace Tuf\Tests\TestHelpers\DurableStorage;

use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * Provides a helper method to load MemoryStorage instance from a path.
 *
 * @see \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage
 */
trait MemoryStorageLoaderTrait
{
    use UtilsTrait;

    /**
     * Uses test fixtures at a given path to populate a memory storage backend.
     *
     * @param string $path
     *     The relative path (within the fixtures directory) for the client
     *     data.
     *
     * @return MemoryStorage
     *     Memory storage containing the test client data.
     *
     * @throws \RuntimeException
     *     Thrown if the relative path is invalid.
     */
    public function memoryStorageFromFixture(string $path) : MemoryStorage
    {
        $storage = new MemoryStorage();

        // Loop through and load files in the given path.
        $fsIterator = new \FilesystemIterator(
            static::getFixturesRealPath($path),
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME
        );
        foreach ($fsIterator as $filename => $info) {
            // Only load JSON files.
            /** @var $info \SplFileInfo */
            if ($info->isFile() && preg_match("|\.json$|", $filename)) {
                $storage[$filename] = file_get_contents($info->getRealPath());
            }
        }

        return $storage;
    }
}

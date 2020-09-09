<?php


namespace Tuf\Tests\TestHelpers\DurableStorage;

trait MemoryStorageLoaderTrait
{
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
        $realpath = realpath(__DIR__ . "/../../../fixtures/$path");
        if ($realpath === false || !is_dir($realpath)) {
            throw new \RuntimeException("Client repository fixtures directory not found at $path");
        }

        $storage = new MemoryStorage();

        // Loop through and load files in the given path.
        $fsIterator = new \FilesystemIterator(
            $realpath,
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

<?php

namespace Tuf\Client;

/**
 * Defines an interface for fetching repo files.
 */
interface RepoFileFetcherInterface
{
    /**
     * Gets a file if it exists in the remote repo.
     *
     * @param string $fileName
     *   The file name to fetch.
     * @param integer $maxBytes
     *   The maximum number of bytes to download.
     *
     * @return string|null
     *   The contents of the file or null if it does not exist.
     */
    public function fetchMetadataIfExists(string $fileName, int $maxBytes): ?string;
}

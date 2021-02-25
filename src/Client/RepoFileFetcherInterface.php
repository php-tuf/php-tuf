<?php

namespace Tuf\Client;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Defines an interface for fetching repo files.
 */
interface RepoFileFetcherInterface
{

    /**
     * Fetches a file from the remote repo.
     *
     * @param string $fileName
     *   The file name.
     *
     * @param integer $maxBytes
     *   The maximum number of bytes to download.
     *
     * @return string
     *   The file contents.
     *
     * @throws \Tuf\Exception\RepoFileNotFound
     *   Thrown if the file is not found.
     *
     * @throws \Tuf\Exception\DownloadSizeException
     *   Thrown if the file exceeds $maxBytes in size.
     */
    public function fetchFile(string $fileName, int $maxBytes): PromiseInterface;

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
    public function fetchFileIfExists(string $fileName, int $maxBytes):?string;
}

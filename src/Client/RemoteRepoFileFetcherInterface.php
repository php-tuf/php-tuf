<?php

namespace Tuf\Client;

/**
 * Defines an interface for fetching remote repo files.
 */
interface RemoteRepoFileFetcherInterface
{

    /**
     * Fetches a file from the remote repo.
     *
     * @param string $fileName
     *   The file name.
     *
     * @param integer $maxBytes
     *   The maximum number of bytes to download
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
    public function fetchFile(string $fileName, int $maxBytes);
}

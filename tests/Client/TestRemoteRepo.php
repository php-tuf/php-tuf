<?php

namespace Tuf\Tests\Client;

use Tuf\Client\RemoteRepoFileFetcherInterface;
use Tuf\Exception\RepoFileNotFound;

class TestRemoteRepo implements RemoteRepoFileFetcherInterface
{

    /**
     * {@inheritdoc}
     */
    public function fetchFile(string $fileName, int $maxBytes)
    {
        try {
            // @todo Ensure the file does not exceed $maxBytes to prevent
            //     DOS attacks.
            $contents = file_get_contents(__DIR__ .  "/../../fixtures/tufrepo/metadata/$fileName");
            if ($contents === false) {
                throw new RepoFileNotFound("File $fileName not found.");
            }
            return $contents;
        } catch (\Exception $exception) {
            return false;
        }
    }
}

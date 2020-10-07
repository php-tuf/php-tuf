<?php

namespace Tuf\Tests\Client;

use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\JsonNormalizer;

class TestRepo implements RepoFileFetcherInterface
{

    /**
     * File names for files that should fail signature checks.
     *
     * @var string[]
     */
    protected $failSignatureFiles = [];

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
            // Alter the signed portion of the json contents to trigger an
            // exception.
            // @see \Tuf\Client\Updater::checkSignatures()
            if (in_array($fileName, $this->failSignatureFiles)) {
                $json = json_decode($contents, true);
                $json['signed']['extra_test_value'] = 'value';
                $contents = JsonNormalizer::asNormalizedJson($json);
            }
            return $contents;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Sets the file for which a signature fail should be triggered.
     *
     * @param array $fileNames
     *   The file names for which a signature fail should be triggered.
     *
     * @return void
     */
    public function setFilesToFailSignature(array $fileNames)
    {
        $this->failSignatureFiles = $fileNames;
    }
}

<?php

namespace Tuf\Tests\Client;

use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\JsonNormalizer;

/**
 * Defines an implementation of RepoFileFetcherInterface to use with test fixtures.
 */
class TestRepo implements RepoFileFetcherInterface
{

    /**
     * The fixtures set to use.
     *
     * @var string
     */
    protected $fixturesSet;

    /**
     * File names for files that should fail signature checks.
     *
     * @var string[]
     */
    protected $failSignatureFiles = [];

    /**
     * TestRepo constructor.
     *
     * @param string $fixturesSet
     *   The fixtures set to use.
     */
    public function __construct(string $fixturesSet)
    {
        $this->fixturesSet = $fixturesSet;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFile(string $fileName, int $maxBytes):string
    {
        try {
            $contents = file_get_contents(__DIR__ .  "/../../fixtures/{$this->fixturesSet}/tufrepo/metadata/$fileName");
            if ($contents === false) {
                throw new RepoFileNotFound("File $fileName not found.");
            }
            // Alter the signed portion of the json contents to trigger an
            // exception.
            // @see \Tuf\Client\Updater::checkSignatures()
            if (in_array($fileName, $this->failSignatureFiles)) {
                $json = json_decode($contents);
                $json->signed->extra_test_value = 'value';
                $contents = json_encode($json);
            }
            return $contents;
        } catch (\Exception $exception) {
            throw new RepoFileNotFound("File $fileName not found.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFileIfExists(string $fileName, int $maxBytes):?string
    {
        try {
            return $this->fetchFile($fileName, $maxBytes);
        } catch (RepoFileNotFound $exception) {
            return null;
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

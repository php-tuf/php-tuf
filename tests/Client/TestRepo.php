<?php

namespace Tuf\Tests\Client;

use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\JsonNormalizer;
use Tuf\Tests\TestHelpers\UtilsTrait;

/**
 * Defines an implementation of RepoFileFetcherInterface to use with test fixtures.
 */
class TestRepo implements RepoFileFetcherInterface
{
    use UtilsTrait;

    /**
     * The fixtures set to use.
     *
     * @var string
     */
    protected $fixturesSet;

    /**
     * File names for files that should fail signature checks.
     *
     * @var mixed
     */
    protected $fileChanges = [];

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
    public function fetchFile(string $fileName, int $maxBytes)
    {
        try {
            $contents = file_get_contents(__DIR__ .  "/../../fixtures/{$this->fixturesSet}/tufrepo/metadata/$fileName");
            if ($contents === false) {
                throw new RepoFileNotFound("File $fileName not found.");
            }
            // Alter the signed portion of the json contents to trigger an
            // exception.
            // @see \Tuf\Client\Updater::checkSignatures()
            if (array_key_exists($fileName, $this->fileChanges)) {
                $json = json_decode($contents, true);
                static::nestedChange($this->fileChanges[$fileName]['keys'], $json, $this->fileChanges[$fileName]['new_value']);
                $contents = JsonNormalizer::asNormalizedJson($json);
            }
            return $contents;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Sets a value in a file to be changed in ::fetchFile().
     *
     * @param string $fileName
     *   The file name to change.
     * @param array $keys
     *   The keys of the nested item.
     * @param mixed $newValue
     *   The new value.
     *
     * @return void
     */
    public function setFileChange(string $fileName, array $keys = ['signed', 'extra_test_value'], $newValue = 'new value')
    {
        $this->fileChanges[$fileName] = ['keys' => $keys, 'new_value' => $newValue];
    }
}

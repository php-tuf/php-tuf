<?php

namespace Tuf\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Tuf\Exception\RepoFileNotFound;
use Tuf\Loader\GuzzleLoader;
use Tuf\Loader\LoaderInterface;
use Tuf\Loader\SizeCheckingLoader;

/**
 * Defines a file fetcher that uses Guzzle to read a file over HTTPS.
 */
class GuzzleFileFetcher implements RepoFileFetcherInterface
{
    public function __construct(private LoaderInterface $metadataLoader)
    {
    }

    /**
     * Creates an instance of this class with a specific base URI.
     *
     * @param string $baseUri
     *   The base URI from which to fetch files.
     * @param string $metadataPrefix
     *   (optional) The path prefix for metadata. Defaults to '/metadata/'.
     * @param string $targetsPrefix
     *   (optional) The path prefix for targets. Defaults to '/targets/'.
     *
     * @return static
     *   A new instance of this class.
     */
    public static function createFromUri(string $baseUri, string $metadataPrefix = '/metadata/', string $targetsPrefix = '/targets/'): self
    {
        $metadataClient = new Client(['base_uri' => $baseUri . $metadataPrefix]);
        $metadataLoader = new GuzzleLoader($metadataClient);
        $metadataLoader = new SizeCheckingLoader($metadataLoader);

        return new static($metadataLoader);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchMetadata(string $fileName, int $maxBytes): PromiseInterface
    {
        return $this->metadataLoader->load($fileName, $maxBytes);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchMetadataIfExists(string $fileName, int $maxBytes): ?string
    {
        try {
            return $this->fetchMetadata($fileName, $maxBytes)->wait();
        } catch (RepoFileNotFound $exception) {
            return null;
        }
    }
}

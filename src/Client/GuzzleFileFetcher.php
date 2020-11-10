<?php

namespace Tuf\Client;

use GuzzleHttp\Client;
use Tuf\JsonNormalizer;

/**
 * Defines a file fetcher that uses Guzzle to read a file over HTTPS.
 */
class GuzzleFileFetcher implements RepoFileFetcherInterface
{
    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * GuzzleFileFetcher constructor.
     *
     * @param string $baseUrl
     *   The base URL from which files will be read.
     */
    public function __construct(string $baseUrl)
    {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if ($scheme === 'https') {
            $this->client = new Client(['base_uri' => $baseUrl]);
        } else {
            throw new \InvalidArgumentException("Repo base URL must be HTTPS: $baseUrl");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFile(string $fileName, int $maxBytes)
    {
        $contents = $this->client->request('GET', $fileName)
            ->getBody()
            ->read($maxBytes);

        $json = json_decode($contents, true);
        return JsonNormalizer::asNormalizedJson($json);
    }
}

<?php

namespace Tuf\Downloader;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;

/**
 * Defines a downloader that checks the length when the promise is fulfilled.
 */
class SizeCheckingDownloader implements DownloaderInterface
{
    public function __construct(private DownloaderInterface $decorated)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function download(string $uri, int $maxBytes = null): PromiseInterface
    {
        $onSuccess = null;

        if (isset($maxBytes)) {
            $onSuccess = function (StreamInterface $data) use ($uri, $maxBytes): StreamInterface {
                $error = new DownloadSizeException("$uri exceeded $maxBytes bytes.");

                // If the stream knows its own length, just ensure it is less
                // than $maxBytes. Otherwise, read $maxBytes of the stream and
                // ensure we've reached the end.
                $size = $data->getSize();
                if ($size === null) {
                    // @todo Handle non-seekable streams.
                    // https://github.com/php-tuf/php-tuf/issues/169
                    $data->rewind();
                    $data->read($maxBytes);

                    // If we're still not at the end of the stream after reading
                    // $maxBytes, it's too long.
                    if ($data->eof() === false) {
                        throw $error;
                    }
                    $data->rewind();
                } elseif ($size > $maxBytes) {
                    throw $error;
                }
                return $data;
            };
        }
        return $this->decorated->download($uri, $maxBytes)
            ->then($onSuccess);
    }
}

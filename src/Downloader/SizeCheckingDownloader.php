<?php

namespace Tuf\Downloader;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;

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
        if (isset($maxBytes)) {
            $onSuccess = function (StreamInterface $data) use ($uri, $maxBytes): StreamInterface {
                $error = new DownloadSizeException("$uri exceeded $maxBytes bytes.");

                $size = $data->getSize();
                if (isset($size)) {
                    if ($size > $maxBytes) {
                        throw $error;
                    }
                } else {
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
                }
                return $data;
            };
        } else {
            $onSuccess = null;
        }
        return $this->decorated->download($uri, $maxBytes)
            ->then($onSuccess);
    }
}

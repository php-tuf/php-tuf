<?php

namespace Tuf\Loader;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;

class SizeCheckingLoader implements LoaderInterface
{
    public function __construct(private LoaderInterface $decorated)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): PromiseInterface
    {
        $onSuccess = null;

        if (isset($maxBytes)) {
            $onSuccess = function (StreamInterface $data) use ($uri, $maxBytes): StreamInterface {
                $error = new DownloadSizeException("$uri exceeded $maxBytes bytes");

                $size = $data->getSize();
                if ($size === null) {
                    // @todo Handle non-seekable streams.
                    // https://github.com/php-tuf/php-tuf/issues/169
                    $data->rewind();
                    $data->read($maxBytes);

                    // If we reached the end of the stream, we didn't exceed the
                    // maximum number of bytes.
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
        return $this->decorated->load($uri, $maxBytes)->then($onSuccess);
    }
}

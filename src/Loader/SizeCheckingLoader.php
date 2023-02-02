<?php

namespace Tuf\Loader;

use Psr\Http\Message\StreamInterface;
use Tuf\Exception\DownloadSizeException;

/**
 * A data loader that enforces a size limit on the output of another loader.
 */
class SizeCheckingLoader implements LoaderInterface
{
    public function __construct(private LoaderInterface $decorated)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $uri, int $maxBytes = null): StreamInterface
    {
        $data = $this->decorated->load($uri, $maxBytes);

        if (isset($maxBytes)) {
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
        }
        return $data;
    }
}

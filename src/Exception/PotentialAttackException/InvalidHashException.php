<?php

namespace Tuf\Exception\PotentialAttackException;

use Psr\Http\Message\StreamInterface;
use Tuf\Exception\TufException;

/**
 * Indicates an invalid hash was computed for a downloaded target.
 *
 * This exception provides access to the underlying stream which caused the
 * error. The stream's contents are NOT trusted by TUF, so any code which
 * interacts with this exception, or the stream, should do so with great caution
 * and treat the stream contents as unsafe.
 */
class InvalidHashException extends TufException
{
    /**
     * An untrusted stream object pointing to the downloaded target.
     *
     * WARNING: The contents of the stream are NOT trusted by TUF! Any code
     * interacting with this exception, or the underlying stream, should proceed
     * with great caution.
     *
     * @var \Psr\Http\Message\StreamInterface
     */
    private $stream;

    /**
     * InvalidHashException constructor.
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     *   An untrusted stream object pointing to the downloaded target. The
     *   contents of this stream are NOT trusted by TUF! Any code interacting
     *   with this exception, or this stream, should proceed with great caution.
     * @param string $message
     *   (optional) The exception message.
     * @param int $code
     *   (optional) The exception code.
     * @param \Throwable|null $previous
     *   The previous exception, if any.
     */
    public function __construct(StreamInterface $stream, $message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->stream = $stream;
    }

    /**
     * Returns the untrusted stream object pointing to the downloaded target.
     *
     * WARNING: The contents of the stream are NOT trusted by TUF! Any code
     * interacting with this exception, or the underlying stream, should proceed
     * with great caution.
     *
     * @return \Psr\Http\Message\StreamInterface
     *   The stream object.
     */
    public function getStream(): StreamInterface
    {
        return $this->stream;
    }
}

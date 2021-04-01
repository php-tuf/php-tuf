<?php

namespace Tuf\Exception\PotentialAttackException;

use Psr\Http\Message\StreamInterface;
use Tuf\Exception\TufException;

/**
 * Indicates an invalid hash was computed for a downloaded target.
 */
class InvalidHashException extends TufException
{
    /**
     * A stream object pointing to the downloaded target.
     *
     * @var \Psr\Http\Message\StreamInterface
     */
    private $stream;

    /**
     * InvalidHashException constructor.
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     *   A stream object pointing to the downloaded target.
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
     * Returns the stream object pointing to the downloaded target.
     *
     * @return \Psr\Http\Message\StreamInterface
     *   The stream object.
     */
    public function getStream(): StreamInterface
    {
        return $this->stream;
    }
}

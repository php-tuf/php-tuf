<?php

namespace Tuf\Client;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Defines an adapter class for a stream that is aware of an HTTP response.
 *
 * This exists because \Tuf\Client\RepoFileFetcherInterface's methods return
 * promises that wrap around instances of StreamInterface, but some consumers
 * of this library might need information about the HTTP response too, if a
 * request was made.
 */
class ResponseAwareStream implements StreamInterface
{
    /**
     * A stream (i.e., the body of an HTTP response).
     *
     * @var \Psr\Http\Message\StreamInterface
     */
    private $stream;

    /**
     * The response that produced the stream.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    private $response;

    /**
     * ResponseAwareStream constructor.
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     *   A stream (i.e., the body of an HTTP response).
     * @param \Psr\Http\Message\ResponseInterface $response
     *   The response that produced the stream.
     */
    public function __construct(StreamInterface $stream, ResponseInterface $response)
    {
        $this->stream = $stream;
        $this->response = $response;
    }

    /**
     * Returns the response that produced this stream.
     *
     * @return \Psr\Http\Message\ResponseInterface
     *    The response.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return $this->stream->__toString();
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        $this->stream->close();
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        return $this->stream->detach();
    }

    /**
     * {@inheritDoc}
     */
    public function getSize()
    {
        return $this->stream->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function tell()
    {
        return $this->stream->tell();
    }

    /**
     * {@inheritDoc}
     */
    public function eof()
    {
        return $this->stream->eof();
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable()
    {
        return $this->stream->isSeekable();
    }

    /**
     * {@inheritDoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        return $this->stream->seek($offset, $whence);
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        return $this->stream->rewind();
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    /**
     * {@inheritDoc}
     */
    public function write($string)
    {
        return $this->stream->write($string);
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    /**
     * {@inheritDoc}
     */
    public function read($length)
    {
        return $this->stream->read($length);
    }

    /**
     * {@inheritDoc}
     */
    public function getContents()
    {
        return $this->stream->getContents();
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata($key = null)
    {
        return $this->stream->getMetadata($key);
    }
}

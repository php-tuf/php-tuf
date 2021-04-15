<?php

namespace Tuf\Client;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Defines an adapter for a stream that comes from an HTTP response.
 *
 * This exists because \Tuf\Client\RepoFileFetcherInterface's methods return
 * promises that wrap around instances of StreamInterface, but some consumers
 * of this library might need information about the HTTP response too, if a
 * request was made.
 */
class ResponseStream implements StreamInterface
{
    /**
     * The response that produced the stream.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    private $response;

    /**
     * ResponseAwareStream constructor.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *   The response that produced the stream.
     */
    public function __construct(ResponseInterface $response)
    {
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
    public function __toString(): string
    {
        return $this->getResponse()->getBody()->__toString();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->getResponse()->getBody()->close();
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        return $this->getResponse()->getBody()->detach();
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): ?int
    {
        return $this->getResponse()->getBody()->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        return $this->getResponse()->getBody()->tell();
    }

    /**
     * {@inheritDoc}
     */
    public function eof(): bool
    {
        return $this->getResponse()->getBody()->eof();
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable(): bool
    {
        return $this->getResponse()->getBody()->isSeekable();
    }

    /**
     * {@inheritDoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        return $this->getResponse()->getBody()->seek($offset, $whence);
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        return $this->getResponse()->getBody()->rewind();
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(): bool
    {
        return $this->getResponse()->getBody()->isWritable();
    }

    /**
     * {@inheritDoc}
     */
    public function write($string): int
    {
        return $this->getResponse()->getBody()->write($string);
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(): bool
    {
        return $this->getResponse()->getBody()->isReadable();
    }

    /**
     * {@inheritDoc}
     */
    public function read($length): string
    {
        return $this->getResponse()->getBody()->read($length);
    }

    /**
     * {@inheritDoc}
     */
    public function getContents(): string
    {
        return $this->getResponse()->getBody()->getContents();
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata($key = null): string
    {
        return $this->getResponse()->getBody()->getMetadata($key);
    }
}

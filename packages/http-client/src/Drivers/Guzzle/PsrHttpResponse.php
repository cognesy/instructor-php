<?php

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class PsrHttpResponse
 *
 * Implements HttpResponse contract for PSR-compatible HTTP client
 */
class PsrHttpResponse implements HttpResponse
{
    private ResponseInterface $response;
    private StreamInterface $stream;
    private EventDispatcherInterface $events;
    private bool $isStreamed;
    private int $streamChunkSize;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
        EventDispatcherInterface $events,
        bool $isStreamed,
        int $streamChunkSize = 256,
    ) {
        $this->response = $response;
        $this->stream = $stream;
        $this->events = $events;
        $this->isStreamed = $isStreamed;
        $this->streamChunkSize = $streamChunkSize;
    }

    /**
     * Get the response status code
     *
     * @return int
     */
    public function statusCode(): int {
        return $this->response->getStatusCode();
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    public function headers(): array {
        return $this->response->getHeaders();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    public function body(): string {
        return $this->response->getBody()->getContents();
    }

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return iterable<string>
     */
    public function stream(?int $chunkSize = null): iterable {
        while (!$this->stream->eof()) {
            $chunk = $this->stream->read($chunkSize ?? $this->streamChunkSize);
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }

    public function isStreamed(): bool {
        return $this->isStreamed;
    }
}

<?php

namespace Cognesy\Polyglot\Http\Adapters;

use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class PsrHttpResponse
 *
 * Implements HttpClientResponse contract for PSR-compatible HTTP client
 */
class PsrHttpResponse implements HttpClientResponse
{
    private ResponseInterface $response;
    private StreamInterface $stream;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
    ) {
        $this->response = $response;
        $this->stream = $stream;
    }

    /**
     * Get the response status code
     *
     * @return int
     */
    public function getStatusCode(): int {
        return $this->response->getStatusCode();
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    public function getHeaders(): array {
        return $this->response->getHeaders();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    public function getContents(): string {
        return $this->response->getBody()->getContents();
    }

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function streamContents(int $chunkSize = 1): Generator {
        while (!$this->stream->eof()) {
            yield $this->stream->read($chunkSize);
        }
    }
}

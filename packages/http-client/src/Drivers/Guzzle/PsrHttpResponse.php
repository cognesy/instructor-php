<?php

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Http\Contracts\HttpClientResponse;
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
    private bool $isStreamed;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
        bool $isStreamed,
    ) {
        $this->response = $response;
        $this->stream = $stream;
        $this->isStreamed = $isStreamed;
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
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator {
        while (!$this->stream->eof()) {
            yield $this->stream->read($chunkSize);
        }
    }

    public function isStreamed(): bool {
        return $this->isStreamed;
    }
}

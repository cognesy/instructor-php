<?php

namespace Cognesy\Http\Adapters;

use Cognesy\Http\Contracts\HttpClientResponse;
use Generator;
use Illuminate\Http\Client\Response;

/**
 * Class LaravelHttpResponse
 *
 * Implements HttpClientResponse contract for Laravel HTTP client
 */
class LaravelHttpResponse implements HttpClientResponse
{
    public function __construct(
        private Response $response,
        private bool $streaming = false
    ) {}

    /**
     * Get the response status code
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->response->status();
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->response->headers();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    public function body(): string
    {
        return $this->response->body();
    }

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator
    {
        if (!$this->streaming) {
            yield $this->body();
            return;
        }

        $stream = $this->response->toPsrResponse()->getBody();
        while (!$stream->eof()) {
            yield $stream->read($chunkSize);
        }
    }
}
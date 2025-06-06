<?php

namespace Cognesy\Http\Middleware\Base;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Generator;

/**
 * Class BaseResponseDecorator
 *
 * A base class for convenient decoration of HttpClientResponse objects
 * by overriding only the methods you need to change:
 * - statusCode() for the HTTP status code
 * - headers() for the HTTP headers
 * - contents() for the full response body
 * - streamContents() for streaming response chunks
 * - toChunk() to transform each chunk in a streamed response
 */
class BaseResponseDecorator implements HttpClientResponse
{
    public function __construct(
        protected HttpClientRequest $request,
        protected HttpClientResponse $response,
    ) {}

    /**
     * Get the response status code
     *
     * @return int
     */
    public function statusCode(): int {
        return $this->response->statusCode();
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    public function headers(): array {
        return $this->response->headers();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    public function body(): string {
        return $this->response->body();
    }

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator {
        foreach ($this->response->stream($chunkSize) as $chunk) {
            yield $this->toChunk($chunk);
        }
    }

    /**
     * Transform a chunk of streamed response content
     *
     * @param string $chunk
     * @return string
     */
    protected function toChunk(string $chunk): string {
        return $chunk;
    }

    public function isStreamed(): bool {
        return $this->response->isStreamed();
    }
}

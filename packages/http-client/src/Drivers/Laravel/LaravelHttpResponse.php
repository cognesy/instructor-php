<?php

namespace Cognesy\Http\Drivers\Laravel;

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

    public function statusCode(): int {
        return $this->response->status();
    }

    public function headers(): array {
        return $this->response->headers();
    }

    public function body(): string {
        return $this->response->body();
    }

    public function isStreamed(): bool {
        return $this->streaming;
    }

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator {
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
<?php

namespace Cognesy\Http\Middleware\BufferResponse;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

/**
 * Middleware that buffers HTTP responses for reuse
 * Ensures that response body and streams can be read multiple times
 */
class BufferResponseMiddleware extends BaseMiddleware
{
    /**
     * Always decorate responses to enable buffering
     */
    protected function shouldDecorateResponse(HttpClientRequest $request, HttpClientResponse $response): bool
    {
        return true;
    }

    /**
     * Create a buffered response decorator
     */
    protected function toResponse(HttpClientRequest $request, HttpClientResponse $response): HttpClientResponse
    {
        return new BufferResponseDecorator($request, $response);
    }
}

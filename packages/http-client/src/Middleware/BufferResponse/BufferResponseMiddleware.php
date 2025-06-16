<?php

namespace Cognesy\Http\Middleware\BufferResponse;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

/**
 * Middleware that buffers HTTP responses for reuse
 * Ensures that response body and streams can be read multiple times
 */
class BufferResponseMiddleware extends BaseMiddleware
{
    /**
     * Always decorate responses to enable buffering
     */
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool
    {
        return true;
    }

    /**
     * Create a buffered response decorator
     */
    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        return new BufferResponseDecorator($request, $response);
    }
}

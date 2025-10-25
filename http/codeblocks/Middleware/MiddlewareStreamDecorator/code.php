<?php

namespace Middleware\MiddlewareStreamDecorator;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Middleware\MiddlewareResponseDecorator\JsonStreamDecorator;

class JsonStreamMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(
        HttpRequest $request,
        HttpResponse $response
    ): bool {
        // Only decorate streaming JSON responses
        return $request->isStreamed() &&
               isset($response->headers()['Content-Type']) &&
               strpos($response->headers()['Content-Type'][0], 'application/json') !== false;
    }

    protected function toResponse(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
        return new JsonStreamDecorator($request, $response);
    }
}

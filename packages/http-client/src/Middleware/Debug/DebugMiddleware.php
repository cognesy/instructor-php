<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

/**
 * DebugMiddleware
 *
 * A middleware that provides debugging capabilities for HTTP requests and responses.
 * It can be used with any HTTP driver that implements the CanHandleHttp interface.
 */
class DebugMiddleware extends BaseMiddleware
{
    public function __construct(
        protected Debug $debug,
    ) {}

    protected function shouldExecute(HttpClientRequest $request): bool {
        return $this->debug->isEnabled();
    }

    protected function beforeRequest(HttpClientRequest $request): void {
        $this->debug->handleRequest($request);
    }

    protected function afterRequest(
        HttpClientRequest  $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        $this->debug->handleResponse($response);
        return $response;
    }

    protected function shouldDecorateResponse(
        HttpClientRequest  $request,
        HttpClientResponse $response,
    ): bool {
        return $request->isStreamed();
    }

    protected function toResponse(
        HttpClientRequest  $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return new DebugResponseDecorator($request, $response, $this->debug);
    }
}
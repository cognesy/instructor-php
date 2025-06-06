<?php

namespace Cognesy\Http\Middleware\Examples;

use Cognesy\Http\Middleware\Base\BaseMiddleware;

class Example3Middleware extends BaseMiddleware
{
    /**
     * beforeRequest() is called right before we send the request downstream.
     * Override to do logging, modify headers, measure start times, etc.
     */
    //protected function beforeRequest(HttpClientRequest $request): void;

    /**
     * afterRequest() is called once we have the raw response from the next handler.
     * Override to transform or log the response before returning it.
     */
    //protected function afterRequest(HttpClientRequest $request, HttpClientResponse $response): HttpClientResponse;

    /**
     * Should we decorate the response to intercept streaming chunks or do
     * additional transformations? By default, returns false. Subclasses
     * can override this to conditionally enable decoration.
     */
    //protected function shouldDecorateResponse(HttpClientRequest $request, HttpClientResponse $response,): bool;

    /**
     * decorateResponse() is called if shouldDecorateResponse() returns true.
     * Default implementation wraps the response in a basic chunk-decorating class
     * that calls processChunk() on every chunk. Override if you need custom logic.
     */
    //protected function toResponse(HttpClientRequest $request, HttpClientResponse $response): HttpClientResponse;
}
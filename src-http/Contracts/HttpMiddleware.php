<?php

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpClientRequest;

/**
 * Interface HttpMiddleware
 *
 * You provide a handle method, which receives the $request
 * and the next $handler in the chain (a CanHandleHttp).
 */
interface HttpMiddleware
{
    /**
     * Intercept or modify the request, optionally return a response
     * immediately, or call $next->handle($request) to continue the chain.
     *
     * Return a HttpClientResponse.
     */
    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse;
}
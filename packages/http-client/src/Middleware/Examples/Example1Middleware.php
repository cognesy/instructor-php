<?php

namespace Cognesy\Http\Middleware\Examples;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

class Example1Middleware implements HttpMiddleware
{
    public function __construct() {}

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Execute code before the next handler in the chain
        // ...

        // Call the next handler in the chain
        $response = $next->handle($request);

        // Execute code after the next handler in the chain
        // ...

        // Decorate the response if we want to log streaming
        return new BaseResponseDecorator($request, $response);
    }
}

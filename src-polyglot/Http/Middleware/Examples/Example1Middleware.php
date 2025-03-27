<?php

namespace Cognesy\Polyglot\Http\Middleware\Examples;

use Cognesy\Polyglot\Http\BaseResponseDecorator;
use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\HttpMiddleware;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;

class Example1Middleware implements HttpMiddleware
{
    public function __construct() {}

    public function handle(HttpClientRequest $request, CanHandleHttp $next): HttpClientResponse
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

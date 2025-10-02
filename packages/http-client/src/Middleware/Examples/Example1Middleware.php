<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Examples;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

class Example1Middleware implements HttpMiddleware
{
    public function __construct() {}

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
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

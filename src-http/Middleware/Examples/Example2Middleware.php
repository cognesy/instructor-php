<?php

namespace Cognesy\Http\Middleware\Examples;

use;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientRequest;
use Generator;

class Example2Middleware implements HttpMiddleware
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
        $param = 'example';
        return new class($response, $param) implements HttpClientResponse {
            public function __construct(
                private HttpClientResponse $wrapped,
                private string $param
            ) {}

            public function statusCode(): int   { return $this->wrapped->statusCode(); }
            public function headers(): array    { return $this->wrapped->headers(); }
            public function body(): string  { return $this->wrapped->body(); }

            public function stream(int $chunkSize = 1): Generator
            {
                foreach ($this->wrapped->stream($chunkSize) as $chunk) {
                    // do something with the chunk e.g. using $this->param
                    yield $chunk;
                }
            }
        };
    }
}

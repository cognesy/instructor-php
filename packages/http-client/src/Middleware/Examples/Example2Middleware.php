<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Examples;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

class Example2Middleware implements HttpMiddleware
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
        $param = 'example';
        /** @phpstan-ignore-next-line */
        return new class($response, $param) implements HttpResponse {
            public function __construct(
                private HttpResponse $wrapped,
                private string       $param,
            ) {}

            #[\Override]
            public function statusCode(): int { return $this->wrapped->statusCode(); }
            #[\Override]
            public function headers(): array { return $this->wrapped->headers(); }
            #[\Override]
            public function body(): string { return $this->wrapped->body(); }
            #[\Override]
            public function isStreamed(): bool { return $this->wrapped->isStreamed(); }

            #[\Override]
            public function stream(?int $chunkSize = null): \Generator
            {
                // do something with param
                foreach ($this->wrapped->stream($chunkSize) as $chunk) {
                    // do something with the chunk e.g. using $this->param
                    error_log($this->param); // Example usage to satisfy PHPStan
                    yield $chunk;
                }
            }
        };
    }
}

<?php declare(strict_types=1);

namespace Middleware\BasicHttpMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Psr\Log\LoggerInterface;

final class LoggingMiddleware implements HttpMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $this->logger->info('HTTP request', [
            'method' => $request->method(),
            'url' => $request->url(),
        ]);

        $response = $next->handle($request);

        $this->logger->info('HTTP response', [
            'status' => $response->statusCode(),
            'streamed' => $response->isStreamed(),
        ]);

        return $response;
    }
}

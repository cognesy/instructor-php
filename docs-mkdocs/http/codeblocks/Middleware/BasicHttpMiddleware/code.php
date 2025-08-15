<?php

namespace Middleware\BasicHttpMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

class LoggingMiddleware implements HttpMiddleware
{
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        // Pre-request logging
        $this->logger->info('Sending request', [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
        ]);

        $startTime = microtime(true);

        // Call the next handler in the chain
        $response = $next->handle($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // in milliseconds

        // Post-response logging
        $this->logger->info('Received response', [
            'status' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
            'duration_ms' => $duration,
        ]);

        // Return the response
        return $response;
    }
}

<?php declare(strict_types=1);

namespace Troubleshooting\CustomLogging;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Psr\Log\LoggerInterface;

final class DetailedLoggingMiddleware implements HttpMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $start = microtime(true);
        $response = $next->handle($request);

        $this->logger->info('HTTP call', [
            'method' => $request->method(),
            'url' => $request->url(),
            'status' => $response->statusCode(),
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ]);

        return $response;
    }
}

<?php declare(strict_types=1);

namespace Troubleshooting\CustomLogging;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Psr\Log\LoggerInterface;

class DetailedLoggingMiddleware extends BaseMiddleware
{
    private LoggerInterface $logger;
    private array $startTimes = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function beforeRequest(HttpRequest $request): void
    {
        $requestId = bin2hex(random_bytes(8));
        $this->startTimes[$requestId] = microtime(true);

        $context = [
            'request_id' => $requestId,
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
        ];

        // Only log the body for non-binary content
        $contentType = $request->headers()['Content-Type'] ?? '';
        $contentType = is_array($contentType) ? ($contentType[0] ?? '') : $contentType;

        if (strpos($contentType, 'application/json') !== false ||
            strpos($contentType, 'text/') === 0) {
            $context['body'] = $request->body()->toString();
        }

        $this->logger->info("HTTP Request: {$request->method()} {$request->url()}", $context);

        // Store the request ID for use in afterRequest
        $request->{__CLASS__} = $requestId;
    }

    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
        $requestId = $request->{__CLASS__} ?? 'unknown';
        $duration = 0;

        if (isset($this->startTimes[$requestId])) {
            $duration = round((microtime(true) - $this->startTimes[$requestId]) * 1000, 2);
            unset($this->startTimes[$requestId]);
        }

        $context = [
            'request_id' => $requestId,
            'status_code' => $response->statusCode(),
            'headers' => $response->headers(),
            'duration_ms' => $duration,
        ];

        // Only log the body for non-binary content and reasonable sizes
        $contentType = $response->headers()['Content-Type'] ?? '';
        $contentType = is_array($contentType) ? ($contentType[0] ?? '') : $contentType;

        if ((strpos($contentType, 'application/json') !== false ||
             strpos($contentType, 'text/') === 0) &&
            strlen($response->body()) < 10000) {
            $context['body'] = $response->body();
        }

        $logLevel = $response->statusCode() >= 400 ? 'error' : 'info';
        $this->logger->log(
            $logLevel,
            "HTTP Response: {$response->statusCode()} from {$request->method()} {$request->url()} ({$duration}ms)",
            $context
        );

        return $response;
    }
}
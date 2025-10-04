<?php declare(strict_types=1);

namespace Troubleshooting\DistributedTracing;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use YourNamespace\Http\Middleware\CanHandleHttpRequest;

class OpenTelemetryMiddleware extends BaseMiddleware
{
    private TracerInterface $tracer;

    public function __construct(TracerInterface $tracer) {
        $this->tracer = $tracer;
    }

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $url = parse_url($request->url());
        $path = $url['path'] ?? '/';
        $host = $url['host'] ?? 'unknown';

        // Create a span for this operation
        $span = $this->tracer
            ->spanBuilder($request->method() . ' ' . $path)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $scope = $span->activate();

        try {
            // Add attributes to the span
            $span->setAttribute('http.method', $request->method());
            $span->setAttribute('http.url', $request->url());
            $span->setAttribute('http.host', $host);
            $span->setAttribute('http.path', $path);

            // Make the actual request
            $response = $next->withRequest($request)->get();

            // Record response information
            $span->setAttribute('http.status_code', $response->statusCode());

            // Set status based on response
            if ($response->statusCode() >= 400) {
                $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::ERROR, "HTTP {$response->statusCode()}");
            }

            return $response;
        } catch (\Exception $e) {
            // Record the exception
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::ERROR, $e->getMessage());

            // Re-throw the exception
            throw $e;
        } finally {
            // End the span
            $scope->detach();
            $span->end();
        }
    }
}

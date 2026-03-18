<?php declare(strict_types=1);

namespace Cognesy\Http\Telemetry;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class TraceContextMiddleware implements HttpMiddleware
{
    public const METADATA_KEY = 'telemetry.trace_context';

    public function __construct(
        private TraceContextPropagator $propagator = new TraceContextPropagator(),
        private string $metadataKey = self::METADATA_KEY,
    ) {}

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $context = $this->resolveContext($request);

        return match ($context) {
            null => $next->handle($request),
            default => $next->handle($this->propagator->inject($request, $context)),
        };
    }

    private function resolveContext(HttpRequest $request): ?TraceContext
    {
        $value = $request->metadata->get($this->metadataKey);

        return match (true) {
            $value instanceof TraceContext => $value,
            is_string($value) && $value !== '' => TraceContext::fromTraceparent($value),
            is_array($value) && isset($value['traceparent']) => TraceContext::fromTraceparent(
                $value['traceparent'],
                $value['tracestate'] ?? null,
            ),
            default => null,
        };
    }
}

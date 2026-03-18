<?php declare(strict_types=1);

namespace Cognesy\Http\Telemetry;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class TraceContextPropagator
{
    public function inject(HttpRequest $request, TraceContext $context): HttpRequest {
        $updated = $request->withHeader('traceparent', $context->traceparent());
        $tracestate = $context->tracestate();

        return match ($tracestate) {
            null, '' => $updated,
            default => $updated->withHeader('tracestate', $tracestate),
        };
    }

    public function extract(HttpRequest $request): ?TraceContext
    {
        $traceparent = $request->headers('traceparent');
        $tracestate = $request->headers('tracestate');

        return match (true) {
            !is_string($traceparent), $traceparent === '' => null,
            is_string($tracestate) && $tracestate !== '' => TraceContext::fromTraceparent($traceparent, $tracestate),
            default => TraceContext::fromTraceparent($traceparent),
        };
    }
}

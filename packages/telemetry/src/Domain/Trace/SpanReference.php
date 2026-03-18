<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Trace;

use Cognesy\Telemetry\Domain\Value\SpanId;
use Cognesy\Telemetry\Domain\Value\TraceFlags;
use Cognesy\Telemetry\Domain\Value\TraceId;

final readonly class SpanReference
{
    public function __construct(
        private TraceId $traceId,
        private SpanId $spanId,
        private ?SpanId $parentSpanId = null,
    ) {}

    public static function fromContext(TraceContext $context, ?SpanId $parentSpanId = null): self {
        return new self(
            traceId: $context->traceId(),
            spanId: $context->spanId(),
            parentSpanId: $parentSpanId,
        );
    }

    public static function childOf(self $parent): self {
        return new self(
            traceId: $parent->traceId,
            spanId: SpanId::generate(),
            parentSpanId: $parent->spanId,
        );
    }

    public function traceId(): TraceId {
        return $this->traceId;
    }

    public function spanId(): SpanId {
        return $this->spanId;
    }

    public function parentSpanId(): ?SpanId {
        return $this->parentSpanId;
    }

    public function asTraceContext(
        ?TraceFlags $traceFlags = null,
        ?string $tracestate = null,
    ): TraceContext {
        return new TraceContext(
            traceId: $this->traceId,
            spanId: $this->spanId,
            traceFlags: $traceFlags ?? TraceFlags::sampled(),
            tracestate: $tracestate,
        );
    }
}

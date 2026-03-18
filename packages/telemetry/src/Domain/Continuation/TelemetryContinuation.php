<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Continuation;

use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class TelemetryContinuation
{
    /** @param array<string, scalar> $correlation */
    public function __construct(
        private TraceContext $context,
        private array $correlation = [],
    ) {}

    public function context(): TraceContext {
        return $this->context;
    }

    /** @return array<string, scalar> */
    public function correlation(): array {
        return $this->correlation;
    }

    /** @return array{traceparent: string, tracestate?: string, correlation?: array<string, scalar>} */
    public function toArray(): array
    {
        $payload = $this->context->toArray();

        return match ($this->correlation === []) {
            true => $payload,
            default => [...$payload, 'correlation' => $this->correlation],
        };
    }

    /** @param array{traceparent: string, tracestate?: string|null, correlation?: array<string, scalar>} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            context: TraceContext::fromTraceparent($data['traceparent'], $data['tracestate'] ?? null),
            correlation: $data['correlation'] ?? [],
        );
    }
}

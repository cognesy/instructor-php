<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Trace;

use Cognesy\Telemetry\Domain\Value\SpanId;
use Cognesy\Telemetry\Domain\Value\TraceFlags;
use Cognesy\Telemetry\Domain\Value\TraceId;
use InvalidArgumentException;

final readonly class TraceContext
{
    public function __construct(
        private TraceId $traceId,
        private SpanId $spanId,
        private TraceFlags $traceFlags,
        private ?string $tracestate = null,
    ) {}

    public static function fresh(bool $sampled = true): self {
        return new self(
            traceId: TraceId::generate(),
            spanId: SpanId::generate(),
            traceFlags: $sampled ? TraceFlags::sampled() : TraceFlags::notSampled(),
        );
    }

    public static function fromTraceparent(string $traceparent, ?string $tracestate = null): self {
        $parts = explode('-', strtolower($traceparent));
        if (count($parts) !== 4 || $parts[0] !== '00') {
            throw new InvalidArgumentException('Traceparent must be in W3C traceparent format.');
        }

        return new self(
            traceId: TraceId::fromString($parts[1]),
            spanId: SpanId::fromString($parts[2]),
            traceFlags: TraceFlags::fromString($parts[3]),
            tracestate: $tracestate,
        );
    }

    public static function childOf(self $parent): self
    {
        return new self(
            traceId: $parent->traceId,
            spanId: SpanId::generate(),
            traceFlags: $parent->traceFlags,
            tracestate: $parent->tracestate,
        );
    }

    /** @param array{traceparent: string, tracestate?: string} $data */
    public static function fromArray(array $data): self
    {
        return self::fromTraceparent($data['traceparent'], $data['tracestate'] ?? null);
    }

    public function traceId(): TraceId {
        return $this->traceId;
    }

    public function spanId(): SpanId {
        return $this->spanId;
    }

    public function traceFlags(): TraceFlags {
        return $this->traceFlags;
    }

    public function tracestate(): ?string {
        return $this->tracestate;
    }

    public function traceparent(): string {
        return sprintf(
            '00-%s-%s-%s',
            $this->traceId->value(),
            $this->spanId->value(),
            $this->traceFlags->value(),
        );
    }

    /** @return array{traceparent: string, tracestate?: string} */
    public function toHeaders(): array {
        $headers = ['traceparent' => $this->traceparent()];
        if ($this->tracestate !== null && $this->tracestate !== '') {
            $headers['tracestate'] = $this->tracestate;
        }
        return $headers;
    }

    /** @return array{traceparent: string, tracestate?: string} */
    public function toArray(): array
    {
        return $this->toHeaders();
    }
}

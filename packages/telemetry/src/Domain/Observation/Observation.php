<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Observation;

use Cognesy\Telemetry\Domain\Trace\SpanReference;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use DateTimeImmutable;

final readonly class Observation
{
    public function __construct(
        private SpanReference $span,
        private string $name,
        private ObservationKind $kind,
        private ObservationStatus $status,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $endedAt,
        private AttributeBag $attributes,
    ) {}

    public static function span(
        SpanReference $span,
        string $name,
        AttributeBag $attributes,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $endedAt = null,
        ObservationStatus $status = ObservationStatus::Ok,
    ): self {
        $start = $startedAt ?? new DateTimeImmutable();
        return new self(
            span: $span,
            name: $name,
            kind: ObservationKind::Span,
            status: $status,
            startedAt: $start,
            endedAt: $endedAt ?? $start,
            attributes: $attributes,
        );
    }

    public static function log(
        SpanReference $span,
        string $name,
        AttributeBag $attributes,
        ?DateTimeImmutable $at = null,
        ObservationStatus $status = ObservationStatus::Ok,
    ): self {
        $time = $at ?? new DateTimeImmutable();
        return new self(
            span: $span,
            name: $name,
            kind: ObservationKind::Log,
            status: $status,
            startedAt: $time,
            endedAt: $time,
            attributes: $attributes,
        );
    }

    public function spanReference(): SpanReference {
        return $this->span;
    }

    public function name(): string {
        return $this->name;
    }

    public function kind(): ObservationKind {
        return $this->kind;
    }

    public function status(): ObservationStatus {
        return $this->status;
    }

    public function startedAt(): DateTimeImmutable {
        return $this->startedAt;
    }

    public function endedAt(): DateTimeImmutable {
        return $this->endedAt;
    }

    public function attributes(): AttributeBag {
        return $this->attributes;
    }
}

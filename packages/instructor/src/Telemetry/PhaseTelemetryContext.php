<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

/**
 * What a structured-output phase needs in order to emit telemetry a projector can consume.
 *
 * Extraction and validation are both deliberately context-free - `ResponseExtractor` and
 * `ResponseValidator` work on content and objects, not on executions, and are usable
 * standalone. Rather than teaching them about structured-output executions, the caller that
 * owns one builds this and hands it down. Absent, both still run and still emit their
 * lifecycle events; the events simply carry no telemetry and are not projectable.
 *
 * The flat ids are kept alongside the envelope because listeners other than the projector -
 * log enrichers, the console reporter - read them directly, and dropping them would trade one
 * reconstruction problem for another.
 */
final readonly class PhaseTelemetryContext
{
    public function __construct(
        private TelemetryEnvelope $envelope,
        private string $executionId,
        private string $phaseId,
        private string $phase,
        private ?string $attemptId = null,
    ) {}

    /**
     * Merged into every lifecycle event payload of the phase at dispatch time.
     *
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        return array_filter([
            'executionId' => $this->executionId,
            'phaseId' => $this->phaseId,
            'phase' => $this->phase,
            'attemptId' => $this->attemptId,
            TelemetryEnvelope::KEY => $this->envelope->toArray(),
        ], static fn(mixed $v): bool => $v !== null);
    }

    public function phaseId(): string
    {
        return $this->phaseId;
    }

    public function envelope(): TelemetryEnvelope
    {
        return $this->envelope;
    }
}

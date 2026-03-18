<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\Logfire;

use Cognesy\Telemetry\Adapters\OTel\CanSendOtelPayloads;
use Cognesy\Telemetry\Adapters\OTel\OtelConfig;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Adapters\OTel\OtelHttpTransport;
use Cognesy\Telemetry\Adapters\OTel\OtelPayloadMapper;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Observation\ObservationKind;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final class LogfireExporter implements CanExportObservations, CanFlushTelemetry
{
    private readonly OtelExporter $exporter;

    public function __construct(
        ?LogfireConfig $config = null,
        ?CanSendOtelPayloads $transport = null,
    ) {
        $otelConfig = match ($config) {
            null => null,
            default => new OtelConfig(
                endpoint: $config->endpoint(),
                serviceName: $config->serviceName(),
                headers: $config->headers(),
            ),
        };

        $resolvedTransport = match (true) {
            $transport !== null => $transport,
            $otelConfig !== null => new OtelHttpTransport($otelConfig),
            default => null,
        };

        if ($resolvedTransport === null) {
            throw new \InvalidArgumentException('LogfireExporter requires either LogfireConfig or transport.');
        }

        $this->exporter = new OtelExporter(
            mapper: new OtelPayloadMapper($config?->serviceName() ?? 'instructor-php'),
            transport: $resolvedTransport,
        );
    }

    #[\Override]
    public function exportObservation(Observation $observation): void {
        $this->exporter->exportObservation($this->decorateObservation($observation));
    }

    /** @return list<Observation> */
    public function observations(): array {
        return $this->exporter->observations();
    }

    /** @return array<string, mixed> */
    public function tracesPayload(): array
    {
        return $this->exporter->tracesPayload();
    }

    #[\Override]
    public function flush(): void
    {
        $this->exporter->flush();
    }

    private function decorateObservation(Observation $observation): Observation
    {
        $attributes = $observation->attributes()
            ->merge($this->logfireAttributes($observation));

        return match ($observation->kind()) {
            ObservationKind::Span => Observation::span(
                span: $observation->spanReference(),
                name: $observation->name(),
                attributes: $attributes,
                startedAt: $observation->startedAt(),
                endedAt: $observation->endedAt(),
                status: $observation->status(),
            ),
            ObservationKind::Log => Observation::log(
                span: $observation->spanReference(),
                name: $observation->name(),
                attributes: $attributes,
                at: $observation->startedAt(),
                status: $observation->status(),
            ),
        };
    }

    private function logfireAttributes(Observation $observation): AttributeBag
    {
        $level = match ($observation->status()) {
            ObservationStatus::Error => 17,
            ObservationStatus::Ok => 9,
        };

        return AttributeBag::empty()
            ->with('logfire.msg', $observation->name())
            ->with('logfire.msg_template', $observation->name())
            ->with('logfire.level_num', $level)
            ->with('logfire.span_type', $observation->kind()->value);
    }
}

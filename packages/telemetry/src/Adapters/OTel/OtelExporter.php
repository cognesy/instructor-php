<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\OTel;

use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;

final class OtelExporter implements CanExportObservations, CanExportMetrics, CanFlushTelemetry
{
    /** @var list<Observation> */
    private array $observations = [];

    public function __construct(
        private readonly OtelPayloadMapper $mapper = new OtelPayloadMapper(),
        private readonly ?CanSendOtelPayloads $transport = null,
    ) {}

    #[\Override]
    public function exportObservation(Observation $observation): void {
        $this->observations[] = $observation;
    }

    /** @param iterable<Metric> $metrics */
    #[\Override]
    public function export(iterable $metrics): void {
        if ($this->transport === null) {
            return;
        }

        $list = is_array($metrics) ? $metrics : iterator_to_array($metrics);
        if ($list !== []) {
            $this->transport->send('metrics', $this->mapper->metricsPayload($list));
        }
    }

    /** @return list<Observation> */
    public function observations(): array {
        return $this->observations;
    }

    /** @return array<string, mixed> */
    public function tracesPayload(): array
    {
        return $this->mapper->tracesPayload($this->observations);
    }

    #[\Override]
    public function flush(): void
    {
        if ($this->transport === null || $this->observations === []) {
            return;
        }

        $this->transport->send('traces', $this->tracesPayload());
    }
}

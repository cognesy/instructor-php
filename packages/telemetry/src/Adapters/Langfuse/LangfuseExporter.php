<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\Langfuse;

use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Telemetry\Adapters\OTel\CanSendOtelPayloads;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;

final class LangfuseExporter implements CanExportObservations, CanExportMetrics, CanFlushTelemetry
{
    /** @var list<Observation> */
    private array $observations = [];

    /** @var list<Metric> */
    private array $metrics = [];

    private readonly ?CanSendOtelPayloads $transport;

    public function __construct(
        private readonly LangfusePayloadMapper $mapper = new LangfusePayloadMapper(),
        ?LangfuseConfig $config = null,
        ?CanSendOtelPayloads $transport = null,
    ) {
        $this->transport = match (true) {
            $transport !== null => $transport,
            $config !== null => new LangfuseHttpTransport($config),
            default => null,
        };
    }

    #[\Override]
    public function exportObservation(Observation $observation): void {
        $this->observations[] = $observation;
    }

    /** @param iterable<Metric> $metrics */
    #[\Override]
    public function export(iterable $metrics): void {
        foreach ($metrics as $metric) {
            $this->metrics[] = $metric;
        }
    }

    /** @return list<Observation> */
    public function observations(): array {
        return $this->observations;
    }

    /** @return array<string, mixed> */
    public function tracesPayload(): array
    {
        return $this->mapper->tracesPayload($this->observations, $this->metrics);
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

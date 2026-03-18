<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Exporter;

use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;

final readonly class CompositeTelemetryExporter implements CanExportObservations, CanExportMetrics, CanFlushTelemetry, CanShutdownTelemetry
{
    /** @param list<CanExportObservations> $exporters */
    public function __construct(
        private array $exporters,
    ) {}

    #[\Override]
    public function exportObservation(Observation $observation): void {
        foreach ($this->exporters as $exporter) {
            $exporter->exportObservation($observation);
        }
    }

    /** @param iterable<Metric> $metrics */
    #[\Override]
    public function export(iterable $metrics): void {
        $list = is_array($metrics) ? $metrics : iterator_to_array($metrics);
        foreach ($this->exporters as $exporter) {
            if ($exporter instanceof CanExportMetrics) {
                $exporter->export($list);
            }
        }
    }

    #[\Override]
    public function flush(): void {
        foreach ($this->exporters as $exporter) {
            if ($exporter instanceof CanFlushTelemetry) {
                $exporter->flush();
            }
        }
    }

    #[\Override]
    public function shutdown(): void {
        foreach ($this->exporters as $exporter) {
            if ($exporter instanceof CanShutdownTelemetry) {
                $exporter->shutdown();
            }
        }
    }
}

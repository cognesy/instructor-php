<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application;

use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Trace\SpanReference;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use DateTimeImmutable;

class Telemetry implements CanFlushTelemetry, CanShutdownTelemetry
{
    /** @var list<Metric> */
    private array $pendingMetrics = [];

    public function __construct(
        private readonly TraceRegistry $registry,
        private readonly CanExportObservations $exporter,
    ) {}

    public function openRoot(
        string $key,
        string $name,
        ?TraceContext $context = null,
        ?AttributeBag $attributes = null,
    ): SpanReference {
        return $this->registry
            ->openRoot($key, $name, $context, $attributes)
            ->reference();
    }

    public function openChild(
        string $key,
        string $parentKey,
        string $name,
        ?AttributeBag $attributes = null,
    ): SpanReference {
        return $this->registry
            ->openChild($key, $parentKey, $name, $attributes)
            ->reference();
    }

    public function log(
        string $key,
        string $name,
        ?AttributeBag $attributes = null,
        ObservationStatus $status = ObservationStatus::Ok,
    ): ?Observation {
        $parent = $this->registry->spanReference($key);
        if ($parent === null) {
            return null;
        }

        $observation = Observation::log(
            span: SpanReference::childOf($parent),
            name: $name,
            attributes: $attributes ?? AttributeBag::empty(),
            status: $status,
        );
        $this->exporter->exportObservation($observation);
        return $observation;
    }

    public function metric(Metric $metric): void {
        $this->pendingMetrics[] = $metric;
    }

    public function spanReference(string $key): ?SpanReference {
        return $this->registry->spanReference($key);
    }

    public function traceContext(string $key): ?TraceContext
    {
        return $this->spanReference($key)?->asTraceContext();
    }

    /** @param array<string, scalar> $correlation */
    public function continuation(string $key, array $correlation = []): ?TelemetryContinuation
    {
        $context = $this->traceContext($key);

        return match ($context) {
            null => null,
            default => new TelemetryContinuation($context, $correlation),
        };
    }

    public function complete(string $key, ?AttributeBag $attributes = null): ?Observation
    {
        return $this->finish($key, ObservationStatus::Ok, $attributes);
    }

    public function fail(string $key, ?AttributeBag $attributes = null): ?Observation
    {
        return $this->finish($key, ObservationStatus::Error, $attributes);
    }

    #[\Override]
    public function flush(): void {
        if ($this->exporter instanceof CanExportMetrics && $this->pendingMetrics !== []) {
            $this->exporter->export($this->pendingMetrics);
            $this->pendingMetrics = [];
        }
        if ($this->exporter instanceof CanFlushTelemetry) {
            $this->exporter->flush();
        }
    }

    #[\Override]
    public function shutdown(): void {
        if ($this->exporter instanceof CanShutdownTelemetry) {
            $this->exporter->shutdown();
        }
    }

    private function finish(string $key, ObservationStatus $status, ?AttributeBag $attributes = null): ?Observation
    {
        $active = $this->registry->close($key);
        if ($active === null) {
            return null;
        }

        $observation = Observation::span(
            span: $active->reference(),
            name: $active->name(),
            attributes: $active->attributes()->merge($attributes ?? AttributeBag::empty()),
            startedAt: $active->startedAt(),
            endedAt: new DateTimeImmutable(),
            status: $status,
        );

        $this->exporter->exportObservation($observation);
        return $observation;
    }
}

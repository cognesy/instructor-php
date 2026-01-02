<?php declare(strict_types=1);

namespace Cognesy\Metrics\Exporters;

use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Log exporter that writes metrics to a PSR-3 logger.
 */
final class LogExporter implements CanExportMetrics
{
    public function __construct(
        private LoggerInterface $logger,
        private string $level = LogLevel::INFO,
    ) {}

    /** @param iterable<Metric> $metrics */
    public function export(iterable $metrics): void {
        foreach ($metrics as $metric) {
            $this->logger->log(
                $this->level,
                sprintf('[%s] %s', $metric->type(), $metric),
                $metric->toArray(),
            );
        }
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Listeners;

use Cognesy\Doctor\Doctest\Events\ValidationStarted;
use Cognesy\Doctor\Doctest\Events\FileValidated;
use Cognesy\Doctor\Doctest\Events\ValidationCompleted;
class ValidationMetricsCollector
{
    private float $startTime = 0;
    private int $totalFiles = 0;
    private int $totalBlocks = 0;
    private int $totalValid = 0;
    private int $totalMissing = 0;
    private float $totalDuration = 0;

    public function handle(object $event): void
    {
        match ($event::class) {
            ValidationStarted::class => $this->onValidationStarted($event),
            FileValidated::class => $this->onFileValidated($event),
            ValidationCompleted::class => $this->onValidationCompleted($event),
            default => null,
        };
    }

    private function onValidationStarted(ValidationStarted $event): void
    {
        $this->startTime = microtime(true);
        $this->reset();
    }

    private function onFileValidated(FileValidated $event): void
    {
        $result = $event->result;
        
        // Always count the file as processed, even if it has no blocks
        $this->totalFiles++;
        $this->totalBlocks += $result->totalBlocks;
        $this->totalValid += $result->validCount();
        $this->totalMissing += $result->missingCount();
        $this->totalDuration += $result->duration;
    }

    private function onValidationCompleted(ValidationCompleted $event): void
    {
        // Final metrics are ready for display
    }

    public function getMetrics(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        
        return [
            'files_processed' => $this->totalFiles,
            'total_blocks' => $this->totalBlocks,
            'valid_blocks' => $this->totalValid,
            'missing_blocks' => $this->totalMissing,
            'total_time' => $totalTime,
            'processing_time' => $this->totalDuration,
        ];
    }

    public function formatSummary(): string
    {
        $metrics = $this->getMetrics();
        
        $status = $metrics['missing_blocks'] > 0 ? "<fg=red>MISSING</>" : "<fg=green>OK</>";
        
        $totalTime = $metrics['total_time'];
        $seconds = floor($totalTime);
        $milliseconds = round(($totalTime - $seconds) * 1000);
        $timeStr = $seconds > 0 ? "{$seconds}s {$milliseconds}ms" : "{$milliseconds}ms";
        
        return "• {$status} {$metrics['files_processed']} files • {$metrics['total_blocks']} blocks • {$metrics['valid_blocks']} valid • {$metrics['missing_blocks']} missing • {$timeStr}";
    }

    private function reset(): void
    {
        $this->totalFiles = 0;
        $this->totalBlocks = 0;
        $this->totalValid = 0;
        $this->totalMissing = 0;
        $this->totalDuration = 0;
    }
}
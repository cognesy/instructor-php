<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Listeners;

use Cognesy\Doctor\Doctest\Events\ExtractionCompleted;
use Cognesy\Doctor\Doctest\Events\ExtractionStarted;
use Cognesy\Doctor\Doctest\Events\FileExtracted;

class ExtractionMetricsCollector
{
    private int $processedFiles = 0;
    private int $snippetsExtracted = 0;
    private float $totalDurationMs = 0.0;
    private bool $sawFileExtracted = false;

    public function handle(object $event): void {
        match ($event::class) {
            ExtractionStarted::class => $this->onStarted($event),
            FileExtracted::class => $this->onFileExtracted($event),
            ExtractionCompleted::class => $this->onCompleted($event),
            default => null,
        };
    }

    private function onStarted(ExtractionStarted $event): void {
        // no-op for now
    }

    private function onFileExtracted(FileExtracted $event): void {
        $this->snippetsExtracted++;
        $this->sawFileExtracted = true;
    }

    private function onCompleted(ExtractionCompleted $event): void {
        $data = is_array($event->data) ? $event->data : [];
        $this->processedFiles += (int)($data['processedFiles'] ?? 0);
        $this->totalDurationMs += (float)($data['durationMs'] ?? 0.0);
        // In dry-run, we may not emit FileExtracted events; accept provided aggregate snippet count
        if (!$this->sawFileExtracted && isset($data['snippetsExtracted'])) {
            $this->snippetsExtracted += (int)$data['snippetsExtracted'];
        }
    }

    public function formatSummary(): string {
        $ms = (int)round($this->totalDurationMs);
        return sprintf(
            '• EXTRACT %d files • %d snippets • %dms',
            $this->processedFiles,
            $this->snippetsExtracted,
            $ms,
        );
    }
}

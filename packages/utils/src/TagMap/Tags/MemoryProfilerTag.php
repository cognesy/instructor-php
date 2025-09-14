<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap\Tags;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * Pure memory tracking tag for pipeline operations.
 *
 * Captures memory usage data without any analysis logic.
 * Consumer components handle leak detection, capacity planning, etc.
 */
readonly class MemoryProfilerTag implements TagInterface
{
    public function __construct(
        public int $startMemory,
        public int $endMemory,
        public int $memoryUsed,        // delta during operation
        public int $startPeakMemory,
        public int $endPeakMemory,
        public int $peakMemoryUsed,    // peak delta during operation
        public ?string $operationName = null,
    ) {}

    /**
     * Get memory used in megabytes for easier readability.
     */
    public function memoryUsedMB(): float {
        return $this->memoryUsed / (1024 * 1024);
    }

    /**
     * Get peak memory used in megabytes.
     */
    public function peakMemoryUsedMB(): float {
        return $this->peakMemoryUsed / (1024 * 1024);
    }

    /**
     * Get memory used in kilobytes.
     */
    public function memoryUsedKB(): float {
        return $this->memoryUsed / 1024;
    }

    /**
     * Get peak memory used in kilobytes.
     */
    public function peakMemoryUsedKB(): float {
        return $this->peakMemoryUsed / 1024;
    }

    /**
     * Get formatted memory usage string with appropriate unit.
     */
    public function memoryUsedFormatted(): string {
        $bytes = abs($this->memoryUsed);
        
        if ($bytes < 1024) {
            return $this->memoryUsed . 'B';
        }
        
        if ($bytes < 1024 * 1024) {
            return number_format($this->memoryUsedKB(), 2) . 'KB';
        }
        
        return number_format($this->memoryUsedMB(), 2) . 'MB';
    }

    /**
     * Get formatted peak memory usage string.
     */
    public function peakMemoryUsedFormatted(): string {
        $bytes = abs($this->peakMemoryUsed);
        
        if ($bytes < 1024) {
            return $this->peakMemoryUsed . 'B';
        }
        
        if ($bytes < 1024 * 1024) {
            return number_format($this->peakMemoryUsedKB(), 2) . 'KB';
        }
        
        return number_format($this->peakMemoryUsedMB(), 2) . 'MB';
    }

    /**
     * Check if memory was freed (negative delta).
     */
    public function isMemoryFreed(): bool {
        return $this->memoryUsed < 0;
    }

    /**
     * Check if memory was allocated (positive delta).
     */
    public function isMemoryAllocated(): bool {
        return $this->memoryUsed > 0;
    }

    /**
     * Convert to array for serialization/logging.
     */
    public function toArray(): array {
        return [
            'operation_name' => $this->operationName,
            'start_memory' => $this->startMemory,
            'end_memory' => $this->endMemory,
            'memory_used_bytes' => $this->memoryUsed,
            'memory_used_mb' => $this->memoryUsedMB(),
            'start_peak_memory' => $this->startPeakMemory,
            'end_peak_memory' => $this->endPeakMemory,
            'peak_memory_used_bytes' => $this->peakMemoryUsed,
            'peak_memory_used_mb' => $this->peakMemoryUsedMB(),
            'memory_used_formatted' => $this->memoryUsedFormatted(),
            'peak_memory_used_formatted' => $this->peakMemoryUsedFormatted(),
        ];
    }
}
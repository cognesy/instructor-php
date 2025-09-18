<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * Pure step-level memory tracking tag.
 *
 * Captures memory usage data for individual pipeline steps
 * without any analysis logic.
 */
readonly class StepMemoryTag implements TagInterface
{
    public function __construct(
        public string $stepName,
        public int $startMemory,
        public int $endMemory,
        public int $memoryUsed,      // delta during step
    ) {}

    /**
     * Get memory used in megabytes for easier readability.
     */
    public function memoryUsedMB(): float {
        return $this->memoryUsed / (1024 * 1024);
    }

    /**
     * Get memory used in kilobytes.
     */
    public function memoryUsedKB(): float {
        return $this->memoryUsed / 1024;
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
            'step_name' => $this->stepName,
            'start_memory' => $this->startMemory,
            'end_memory' => $this->endMemory,
            'memory_used_bytes' => $this->memoryUsed,
            'memory_used_mb' => $this->memoryUsedMB(),
            'memory_used_formatted' => $this->memoryUsedFormatted(),
        ];
    }
}
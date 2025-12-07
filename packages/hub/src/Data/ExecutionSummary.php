<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExecutionSummary
{
    public function __construct(
        public readonly int $totalExamples,
        public readonly int $executed,
        public readonly int $completed,
        public readonly int $errors,
        public readonly int $skipped,
        public readonly int $interrupted,
        public readonly float $averageTime,
        public readonly float $totalTime,
        public readonly ?array $slowestExample = null,
        public readonly ?array $fastestExample = null,
        public readonly ?string $lastFullRun = null,
        public readonly ?string $lastPartialRun = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            totalExamples: 0,
            executed: 0,
            completed: 0,
            errors: 0,
            skipped: 0,
            interrupted: 0,
            averageTime: 0.0,
            totalTime: 0.0,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            totalExamples: $data['totalExamples'] ?? 0,
            executed: $data['totalExecuted'] ?? 0,
            completed: $data['completed'] ?? 0,
            errors: $data['errors'] ?? 0,
            skipped: $data['skipped'] ?? 0,
            interrupted: $data['interrupted'] ?? 0,
            averageTime: $data['averageExecutionTime'] ?? 0.0,
            totalTime: $data['totalExecutionTime'] ?? 0.0,
            slowestExample: $data['slowestExample'] ?? null,
            fastestExample: $data['fastestExample'] ?? null,
            lastFullRun: $data['lastFullRun'] ?? null,
            lastPartialRun: $data['lastPartialRun'] ?? null,
        );
    }

    public function successRate(): float
    {
        return $this->executed > 0
            ? round(($this->completed / $this->executed) * 100, 2)
            : 0.0;
    }

    public function errorRate(): float
    {
        return $this->executed > 0
            ? round(($this->errors / $this->executed) * 100, 2)
            : 0.0;
    }

    public function pending(): int
    {
        return $this->totalExamples - $this->executed;
    }

    public function toArray(): array
    {
        return [
            'totalExamples' => $this->totalExamples,
            'totalExecuted' => $this->executed,
            'completed' => $this->completed,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'interrupted' => $this->interrupted,
            'averageExecutionTime' => $this->averageTime,
            'totalExecutionTime' => $this->totalTime,
            'slowestExample' => $this->slowestExample,
            'fastestExample' => $this->fastestExample,
            'lastFullRun' => $this->lastFullRun,
            'lastPartialRun' => $this->lastPartialRun,
        ];
    }
}

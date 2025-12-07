<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExecutionResult
{
    public function __construct(
        public readonly ExecutionStatus $status,
        public readonly float $executionTime,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly ?ExecutionError $error,
        public readonly \DateTimeImmutable $timestamp,
    ) {}

    public static function success(float $executionTime, string $output): self
    {
        return new self(
            status: ExecutionStatus::COMPLETED,
            executionTime: $executionTime,
            exitCode: 0,
            output: $output,
            error: null,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function failure(float $executionTime, ExecutionError $error): self
    {
        return new self(
            status: ExecutionStatus::ERROR,
            executionTime: $executionTime,
            exitCode: $error->exitCode,
            output: $error->fullOutput,
            error: $error,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function interrupted(float $executionTime): self
    {
        return new self(
            status: ExecutionStatus::INTERRUPTED,
            executionTime: $executionTime,
            exitCode: 130, // Standard SIGINT exit code
            output: '',
            error: null,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function skipped(string $reason = ''): self
    {
        return new self(
            status: ExecutionStatus::SKIPPED,
            executionTime: 0.0,
            exitCode: 0,
            output: $reason,
            error: null,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isError(): bool
    {
        return $this->status->isError();
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'executionTime' => $this->executionTime,
            'exitCode' => $this->exitCode,
            'output' => $this->output,
            'error' => $this->error?->toArray(),
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}

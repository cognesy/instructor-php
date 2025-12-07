<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExampleExecutionStatus
{
    /** @param array<ExecutionError> $errors */
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly string $group,
        public readonly string $relativePath,
        public readonly string $absolutePath,
        public readonly ExecutionStatus $status,
        public readonly ?\DateTimeImmutable $lastExecuted,
        public readonly float $executionTime,
        public readonly int $attempts,
        public readonly array $errors,
        public readonly string $output,
        public readonly int $exitCode,
    ) {}

    public static function pending(Example $example): self
    {
        return new self(
            index: $example->index,
            name: $example->name,
            group: $example->group,
            relativePath: $example->relativePath,
            absolutePath: $example->runPath,
            status: ExecutionStatus::PENDING,
            lastExecuted: null,
            executionTime: 0.0,
            attempts: 0,
            errors: [],
            output: '',
            exitCode: 0,
        );
    }

    public static function fromArray(array $data): self
    {
        $errors = array_map(
            fn(array $e) => ExecutionError::fromArray($e),
            $data['errors'] ?? []
        );

        return new self(
            index: $data['index'] ?? 0,
            name: $data['name'] ?? '',
            group: $data['group'] ?? '',
            relativePath: $data['relativePath'] ?? '',
            absolutePath: $data['absolutePath'] ?? '',
            status: ExecutionStatus::tryFrom($data['status'] ?? 'pending') ?? ExecutionStatus::PENDING,
            lastExecuted: isset($data['lastExecuted']) && $data['lastExecuted']
                ? new \DateTimeImmutable($data['lastExecuted'])
                : null,
            executionTime: $data['executionTime'] ?? 0.0,
            attempts: $data['attempts'] ?? 0,
            errors: $errors,
            output: $data['output'] ?? '',
            exitCode: $data['exitCode'] ?? 0,
        );
    }

    public function isStale(): bool
    {
        if (!$this->lastExecuted || !file_exists($this->absolutePath)) {
            return true;
        }

        $fileModifiedTime = filemtime($this->absolutePath);
        if ($fileModifiedTime === false) {
            return true;
        }

        $fileModified = new \DateTimeImmutable('@' . $fileModifiedTime);
        return $fileModified > $this->lastExecuted;
    }

    public function hasRecentError(?\DateInterval $interval = null): bool
    {
        $interval ??= new \DateInterval('P1D'); // Default: 1 day
        $cutoff = (new \DateTimeImmutable())->sub($interval);

        return $this->status === ExecutionStatus::ERROR
            && $this->lastExecuted !== null
            && $this->lastExecuted > $cutoff;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExecutionStatus::COMPLETED;
    }

    public function isError(): bool
    {
        return $this->status === ExecutionStatus::ERROR;
    }

    public function isPending(): bool
    {
        return $this->status === ExecutionStatus::PENDING;
    }

    public function wasInterrupted(): bool
    {
        return $this->status === ExecutionStatus::INTERRUPTED;
    }

    public function needsExecution(): bool
    {
        return $this->status->needsExecution() || $this->isStale();
    }

    public function lastError(): ?ExecutionError
    {
        return $this->errors[count($this->errors) - 1] ?? null;
    }

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'name' => $this->name,
            'group' => $this->group,
            'relativePath' => $this->relativePath,
            'absolutePath' => $this->absolutePath,
            'status' => $this->status->value,
            'lastExecuted' => $this->lastExecuted?->format('c'),
            'executionTime' => $this->executionTime,
            'attempts' => $this->attempts,
            'errors' => array_map(fn(ExecutionError $e) => $e->toArray(), $this->errors),
            'output' => $this->output,
            'exitCode' => $this->exitCode,
        ];
    }
}

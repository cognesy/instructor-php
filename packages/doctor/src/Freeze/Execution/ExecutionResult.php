<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze\Execution;

class ExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly string $command,
        public readonly int $exitCode = 0,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return !$this->success;
    }
}
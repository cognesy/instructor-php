<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;

final class ExecutionLoop
{
    private StructuredOutputExecution $execution;
    private ?StructuredOutputResponse $pendingEmission = null;
    private bool $terminated = false;

    public function __construct(StructuredOutputExecution $execution)
    {
        $this->execution = $execution;
    }

    public function hasNextEmission(callable $advance): bool
    {
        if (!$this->terminated) {
            $this->advanceUntilEmission($advance);
        }

        return $this->pendingEmission !== null;
    }

    public function nextEmission(callable $advance): ?StructuredOutputResponse
    {
        if (!$this->terminated) {
            $this->advanceUntilEmission($advance);
        }

        $emission = $this->pendingEmission;
        $this->pendingEmission = null;

        return $emission;
    }

    public function execution(): StructuredOutputExecution
    {
        return $this->execution;
    }

    public function replaceExecution(StructuredOutputExecution $execution): void
    {
        $this->execution = $execution;
    }

    public function emit(StructuredOutputResponse $response): void
    {
        $this->pendingEmission = $response;
    }

    public function applyAttemptResult(AttemptProcessingResult $result): void
    {
        $this->execution = $result->execution();
        $this->pendingEmission = $result->response();
        $this->terminated = !$result->shouldRetry();
    }

    public function terminate(): void
    {
        $this->terminated = true;
    }

    public function shouldStopAttempts(): bool
    {
        return $this->execution->isFinalized() || $this->execution->maxRetriesReached();
    }

    private function advanceUntilEmission(callable $advance): void
    {
        if ($this->pendingEmission !== null || $this->terminated) {
            return;
        }

        while ($this->pendingEmission === null && !$this->terminated) {
            $advance($this);
        }
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;

/**
 * Lightweight streaming contract that yields emissions directly,
 * avoiding per-emission StructuredOutputExecution copy chains.
 */
interface CanEmitStreamingUpdates
{
    public function hasNextEmission(): bool;
    public function nextEmission(): ?StructuredOutputResponse;
    public function execution(): StructuredOutputExecution;
}

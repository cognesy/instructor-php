<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;

/**
 * Pull-based execution-driver contract: the caller repeatedly asks whether
 * another emission is available and pulls it, avoiding per-emission
 * StructuredOutputExecution copy chains.
 */
interface CanDriveExecution
{
    public function hasNextEmission(): bool;
    public function nextEmission(): ?StructuredOutputResponse;
    public function execution(): StructuredOutputExecution;
}

<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;
use Cognesy\InstructorHub\Data\ExampleExecutionStatus;
use Cognesy\InstructorHub\Data\ExecutionSummary;

interface CanTrackExecution
{
    public function recordStart(Example $example): void;

    public function recordResult(Example $example, ExecutionResult $result): void;

    public function getStatus(Example $example): ?ExampleExecutionStatus;

    /** @return array<ExampleExecutionStatus> */
    public function getAllStatuses(): array;

    public function getSummary(): ExecutionSummary;

    public function hasStatus(Example $example): bool;

    public function markInterrupted(Example $example, float $executionTime): void;

    public function save(): void;
}

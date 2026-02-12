<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Contracts\CanPersistStatus;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;
use Cognesy\InstructorHub\Data\ExampleExecutionStatus;
use Cognesy\InstructorHub\Data\ExecutionSummary;
use Cognesy\InstructorHub\Data\ExecutionStatus;

class ExecutionTracker implements CanTrackExecution
{
    /** @var array<string, ExampleExecutionStatus> keyed by example short ID */
    private array $statusById = [];
    private array $metadata = [];
    private bool $modified = false;

    public function __construct(
        private CanPersistStatus $repository,
        private ExampleRepository $examples,
        private bool $autoSave = true,
    ) {
        $this->loadStatusData();
    }

    private function loadStatusData(): void
    {
        $data = $this->repository->load();

        $this->metadata = $data['metadata'] ?? [
            'version' => '2.0',
            'lastUpdated' => (new \DateTimeImmutable())->format('c'),
            'totalExamples' => 0,
        ];

        $this->statusById = [];
        foreach ($data['examples'] ?? [] as $statusArray) {
            $status = ExampleExecutionStatus::fromArray($statusArray);
            $key = !empty($status->docName) ? $status->docName : (string) $status->index;
            $this->statusById[$key] = $status;
        }
    }

    #[\Override]
    public function recordStart(Example $example): void
    {
        $key = $this->exampleKey($example);

        // Get existing status or create new one
        $currentStatus = $this->statusById[$key]
            ?? new ExampleExecutionStatus(
                index: $example->index,
                name: $example->name,
                group: $example->group,
                docName: $example->docName,
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

        // Create new status with running state
        $this->statusById[$key] = new ExampleExecutionStatus(
            index: $example->index,
            name: $example->name,
            group: $example->group,
            docName: $example->docName,
            relativePath: $example->relativePath,
            absolutePath: $example->runPath,
            status: ExecutionStatus::RUNNING,
            lastExecuted: $currentStatus->lastExecuted,
            executionTime: $currentStatus->executionTime,
            attempts: $currentStatus->attempts,
            errors: $currentStatus->errors,
            output: $currentStatus->output,
            exitCode: $currentStatus->exitCode,
        );

        $this->modified = true;

        if ($this->autoSave) {
            $this->save();
        }
    }

    #[\Override]
    public function recordResult(Example $example, ExecutionResult $result): void
    {
        $key = $this->exampleKey($example);

        // Get existing status or create new one
        $currentStatus = $this->statusById[$key]
            ?? new ExampleExecutionStatus(
                index: $example->index,
                name: $example->name,
                group: $example->group,
                docName: $example->docName,
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

        // Handle errors - keep only last 5
        $newErrors = $currentStatus->errors;
        if ($result->error) {
            $newErrors[] = $result->error;
            $newErrors = array_slice($newErrors, -5);
        }

        // Create new status with updated data
        $this->statusById[$key] = new ExampleExecutionStatus(
            index: $example->index,
            name: $example->name,
            group: $example->group,
            docName: $example->docName,
            relativePath: $example->relativePath,
            absolutePath: $example->runPath,
            status: $result->status,
            lastExecuted: $result->timestamp,
            executionTime: $result->executionTime,
            attempts: $currentStatus->attempts + 1,
            errors: $newErrors,
            output: $this->truncateOutput($result->output),
            exitCode: $result->exitCode,
        );

        $this->updateMetadata();
        $this->modified = true;

        if ($this->autoSave) {
            $this->save();
        }
    }

    #[\Override]
    public function markInterrupted(Example $example, float $executionTime): void
    {
        $result = ExecutionResult::interrupted($executionTime);
        $this->recordResult($example, $result);
    }

    #[\Override]
    public function getStatus(Example $example): ?ExampleExecutionStatus
    {
        $key = $this->exampleKey($example);
        return $this->statusById[$key] ?? null;
    }

    #[\Override]
    public function hasStatus(Example $example): bool
    {
        $key = $this->exampleKey($example);
        return isset($this->statusById[$key]);
    }

    #[\Override]
    public function getAllStatuses(): array
    {
        return array_values($this->statusById);
    }

    #[\Override]
    public function getSummary(): ExecutionSummary
    {
        // Calculate statistics from current DTOs
        $stats = $this->calculateStatistics(array_values($this->statusById));

        return ExecutionSummary::fromArray($stats);
    }

    #[\Override]
    public function save(): void
    {
        if ($this->modified) {
            // Convert DTOs to arrays for storage
            $examples = [];
            foreach ($this->statusById as $key => $status) {
                $examples[$key] = $status->toArray();
            }

            $dataToSave = [
                'metadata' => $this->metadata,
                'examples' => $examples,
                'statistics' => $this->calculateStatistics(array_values($this->statusById)),
            ];

            $this->repository->save($dataToSave);
            $this->modified = false;
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    public function clearAllStatuses(): void
    {
        $this->statusById = [];
        $this->metadata = [
            'version' => '2.0',
            'lastUpdated' => (new \DateTimeImmutable())->format('c'),
            'totalExamples' => 0,
        ];
        $this->modified = true;
        $this->save();
    }

    public function removeStatus(string $key): void
    {
        if (isset($this->statusById[$key])) {
            unset($this->statusById[$key]);
            $this->updateMetadata();
            $this->modified = true;
            if ($this->autoSave) {
                $this->save();
            }
        }
    }

    public function removeCompletedStatuses(): int
    {
        $removed = 0;
        foreach ($this->statusById as $key => $status) {
            if ($status->status === ExecutionStatus::COMPLETED) {
                unset($this->statusById[$key]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->updateMetadata();
            $this->modified = true;
            if ($this->autoSave) {
                $this->save();
            }
        }

        return $removed;
    }

    public function removeOlderThan(\DateInterval $interval): int
    {
        $cutoff = (new \DateTimeImmutable())->sub($interval);
        $removed = 0;

        foreach ($this->statusById as $key => $status) {
            if ($status->lastExecuted !== null && $status->lastExecuted < $cutoff) {
                unset($this->statusById[$key]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->updateMetadata();
            $this->modified = true;
            if ($this->autoSave) {
                $this->save();
            }
        }

        return $removed;
    }

    private function exampleKey(Example $example): string
    {
        if (!empty($example->id)) {
            return $example->id;
        }
        // Fallback for examples without IDs yet
        return $example->docName;
    }

    private function updateMetadata(): void
    {
        $this->metadata = [
            'version' => '2.0',
            'lastUpdated' => (new \DateTimeImmutable())->format('c'),
            'totalExamples' => count($this->statusById),
        ];
    }

    /** @param array<ExampleExecutionStatus> $statuses */
    private function calculateStatistics(array $statuses): array
    {
        $completed = array_filter($statuses, fn(ExampleExecutionStatus $s) => $s->status === ExecutionStatus::COMPLETED);
        $errors = array_filter($statuses, fn(ExampleExecutionStatus $s) => $s->status === ExecutionStatus::ERROR);
        $interrupted = array_filter($statuses, fn(ExampleExecutionStatus $s) => $s->status === ExecutionStatus::INTERRUPTED);
        $skipped = array_filter($statuses, fn(ExampleExecutionStatus $s) => $s->status === ExecutionStatus::SKIPPED);

        // Only calculate timing stats for successful executions
        $completedTimes = array_map(fn(ExampleExecutionStatus $s) => $s->executionTime, $completed);
        $totalTime = array_sum($completedTimes);
        $avgTime = count($completed) > 0 ? $totalTime / count($completed) : 0.0;

        $slowest = null;
        $fastest = null;

        $validCompletedTimes = array_filter($completedTimes, fn($t) => $t > 0);
        if (!empty($validCompletedTimes)) {
            $maxTime = max($validCompletedTimes);
            $minTime = min($validCompletedTimes);

            // Only consider completed examples for slowest/fastest
            foreach ($completed as $status) {
                if ($status->executionTime === $maxTime && $slowest === null) {
                    $slowest = [
                        'name' => $status->name,
                        'time' => $maxTime,
                        'path' => $status->relativePath
                    ];
                }
                if ($status->executionTime === $minTime && $fastest === null) {
                    $fastest = [
                        'name' => $status->name,
                        'time' => $minTime,
                        'path' => $status->relativePath
                    ];
                }
            }
        }

        return [
            'totalExamples' => count($statuses),
            'totalExecuted' => count($statuses),
            'completed' => count($completed),
            'errors' => count($errors),
            'skipped' => count($skipped),
            'interrupted' => count($interrupted),
            'averageExecutionTime' => round($avgTime, 4),
            'totalExecutionTime' => round($totalTime, 4),
            'lastFullRun' => $this->getLastFullRun($statuses),
            'lastPartialRun' => (new \DateTimeImmutable())->format('c'),
            'slowestExample' => $slowest,
            'fastestExample' => $fastest,
        ];
    }

    /** @param array<ExampleExecutionStatus> $statuses */
    private function getLastFullRun(array $statuses): null
    {
        // This would need to track when all examples were run
        // For now, return null
        return null;
    }

    private function truncateOutput(string $output): string
    {
        // Limit output to prevent huge status files
        $maxLength = 2000;

        if (strlen($output) <= $maxLength) {
            return $output;
        }

        return substr($output, 0, $maxLength) . "\n... (truncated)";
    }
}

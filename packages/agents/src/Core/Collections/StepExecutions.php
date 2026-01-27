<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Collections;

use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Polyglot\Inference\Data\Usage;

/**
 * Immutable collection of step results.
 *
 * Each StepExecution bundles an AgentStep with its ContinuationOutcome,
 * representing the result of a single execution step.
 */
final readonly class StepExecutions
{
    /** @var list<StepExecution> */
    private array $items;

    /**
     * @param list<StepExecution> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param list<StepExecution> $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    public function append(StepExecution $result): self
    {
        return new self([...$this->items, $result]);
    }

    public function last(): ?StepExecution
    {
        if ($this->items === []) {
            return null;
        }
        return $this->items[array_key_last($this->items)];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<StepExecution>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the step result at a specific index.
     */
    public function at(int $index): ?StepExecution
    {
        return $this->items[$index] ?? null;
    }

    /**
     * Get the continuation outcome from the last step result.
     */
    public function lastOutcome(): ?ContinuationOutcome
    {
        return $this->last()?->outcome;
    }

    /**
     * Check if execution should continue based on the last step result.
     */
    public function shouldContinue(): bool
    {
        return $this->last()?->shouldContinue() ?? false;
    }

    /**
     * Total duration of all steps in seconds (cumulative execution time).
     */
    public function totalDuration(): float
    {
        return array_sum(array_map(
            static fn(StepExecution $result): float => $result->duration(),
            $this->items,
        ));
    }

    /**
     * Aggregate token usage across all recorded step executions.
     */
    public function totalUsage(): Usage
    {
        $usage = Usage::none();
        foreach ($this->items as $execution) {
            $usage = $usage->withAccumulated($execution->step->usage());
        }
        return $usage;
    }

    /**
     * Extract all steps from the step results.
     */
    public function steps(): AgentSteps
    {
        $steps = array_map(
            static fn(StepExecution $result): AgentStep => $result->step,
            $this->items,
        );
        return new AgentSteps(...$steps);
    }

    /**
     * Get a step at a specific index.
     */
    public function stepAt(int $index): ?AgentStep
    {
        return $this->items[$index]?->step ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn(StepExecution $result): array => $result->toArray(),
            $this->items,
        );
    }

    /**
     * Deserialize from array.
     *
     * @param array<int, array<string, mixed>> $data
     */
    public static function deserialize(array $data): self
    {
        $items = array_map(
            static fn(array $resultData): StepExecution => StepExecution::fromArray($resultData),
            $data,
        );
        return new self($items);
    }
}

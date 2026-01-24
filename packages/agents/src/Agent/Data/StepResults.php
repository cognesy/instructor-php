<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Data;

use Cognesy\Agents\Agent\Collections\AgentSteps;
use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;

/**
 * Immutable collection of step results.
 *
 * Each StepResult bundles an AgentStep with its ContinuationOutcome,
 * representing the result of a single execution step.
 */
final readonly class StepResults
{
    /** @var list<StepResult> */
    private array $items;

    /**
     * @param list<StepResult> $items
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
     * @param list<StepResult> $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    public function append(StepResult $result): self
    {
        return new self([...$this->items, $result]);
    }

    public function last(): ?StepResult
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
     * @return list<StepResult>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the step result at a specific index.
     */
    public function at(int $index): ?StepResult
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
            static fn(StepResult $result): float => $result->duration(),
            $this->items,
        ));
    }

    /**
     * Extract all steps from the step results.
     */
    public function steps(): AgentSteps
    {
        $steps = array_map(
            static fn(StepResult $result): AgentStep => $result->step,
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
            static fn(StepResult $result): array => $result->toArray(),
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
            static fn(array $resultData): StepResult => StepResult::fromArray($resultData),
            $data,
        );
        return new self($items);
    }
}

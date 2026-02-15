<?php declare(strict_types=1);

namespace Cognesy\Agents\Collections;

use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Data\StepExecution;
use Cognesy\Polyglot\Inference\Data\Usage;

final readonly class StepExecutions
{
    /** @var list<StepExecution> */
    private array $items;

    /**
     * @param list<StepExecution> $items
     */
    public function __construct(array $items = []) {
        $this->items = $items;
    }

    public static function empty(): self {
        return new self([]);
    }

    // MUTATORS /////////////////////////////////////////////////////////

    public function withStepExecution(StepExecution $result): self {
        return new self([...$this->items, $result]);
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function count(): int {
        return count($this->items);
    }

    public function isEmpty(): bool {
        return $this->items === [];
    }

    /**
     * @return list<StepExecution>
     */
    public function all(): array {
        return $this->items;
    }

    public function last() : ?StepExecution {
        if ($this->items === []) {
            return null;
        }
        return $this->items[count($this->items) - 1];
    }

    public function totalDuration(): float {
        return array_sum(array_map(
            static fn(StepExecution $stepExecution): float => $stepExecution->duration(),
            $this->items,
        ));
    }

    public function totalUsage(): Usage {
        $usage = Usage::none();
        foreach ($this->items as $stepExecution) {
            $usage = $usage->withAccumulated($stepExecution->usage());
        }
        return $usage;
    }

    public function steps(): AgentSteps {
        $steps = array_map(
            static fn(StepExecution $stepExecution): AgentStep => $stepExecution->step(),
            $this->items,
        );
        return new AgentSteps(...$steps);
    }

    public function errors(): ErrorList {
        $errors = ErrorList::empty();
        foreach ($this->items as $stepExecution) {
            $errors = $errors->withMergedErrorList($stepExecution->step()->errors());
        }
        return $errors;
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return array_map(
            static fn(StepExecution $result): array => $result->toArray(),
            $this->items,
        );
    }

    public static function fromArray(array $data): self {
        $items = array_map(
            static fn(array $resultData): StepExecution => StepExecution::fromArray($resultData),
            $data,
        );
        return new self($items);
    }
}

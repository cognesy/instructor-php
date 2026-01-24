<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Collections;

use ArrayIterator;
use Cognesy\Agents\Agent\Data\AgentStep;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of agent steps.
 *
 * @implements IteratorAggregate<int, AgentStep>
 */
final readonly class AgentSteps implements Countable, IteratorAggregate
{
    /** @var AgentStep[] */
    private array $steps;

    public function __construct(AgentStep ...$steps) {
        $this->steps = $steps;
    }

    // FACTORY METHODS /////////////////////////////////////////////

    public static function fromArray(array $data): self {
        $steps = array_map(fn($stepData) => AgentStep::fromArray($stepData), $data);
        return new self(...$steps);
    }

    // ACCESSORS ///////////////////////////////////////////////////

    /** @return AgentStep[] */
    public function all(): array {
        return $this->steps;
    }

    public function isEmpty(): bool {
        return $this->steps === [];
    }

    public function lastStep(): ?AgentStep {
        if ($this->count() === 0) {
            return null;
        }
        return $this->steps[array_key_last($this->steps)] ?? null;
    }

    public function stepAt(int $index): ?AgentStep {
        return $this->steps[$index] ?? null;
    }

    // ITERATORS ///////////////////////////////////////////////////

    /** @return Traversable<int, AgentStep> */
    #[\Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->steps);
    }

    #[\Override]
    public function count(): int {
        return count($this->steps);
    }

    // MUTATORS ////////////////////////////////////////////////////

    public function withAddedStep(AgentStep $step): self {
        return new self(...[...$this->steps, $step]);
    }

    public function withAddedSteps(AgentStep ...$steps): self {
        return new self(...[...$this->steps, ...$steps]);
    }

    // TRANSFORMERS AND CONVERSIONS ////////////////////////////////

    /** @return iterable<AgentStep> */
    public function reversed(): iterable {
        foreach (array_reverse($this->steps) as $step) {
            yield $step;
        }
    }

    public function toArray(): array {
        return array_map(fn(AgentStep $step) => $step->toArray(), $this->all());
    }
}

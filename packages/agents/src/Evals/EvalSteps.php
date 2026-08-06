<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/**
 * Immutable ordered collection of `EvalStep`, preserving order across turns.
 *
 * @implements IteratorAggregate<int, EvalStep>
 */
final readonly class EvalSteps implements Countable, IteratorAggregate
{
    /** @var list<EvalStep> */
    private array $steps;

    public function __construct(EvalStep ...$steps) {
        $this->steps = $steps;
    }

    public static function none(): self {
        return new self();
    }

    public function with(EvalStep ...$steps): self {
        return new self(...[...$this->steps, ...$steps]);
    }

    /** @return list<EvalStep> */
    public function all(): array {
        return $this->steps;
    }

    public function last(): ?EvalStep {
        return $this->steps === [] ? null : $this->steps[array_key_last($this->steps)];
    }

    public function usage(): InferenceUsage {
        $usage = InferenceUsage::none();
        foreach ($this->steps as $step) {
            $usage = $usage->withAccumulated($step->usage());
        }
        return $usage;
    }

    public function duration(): float {
        return array_sum(array_map(
            static fn (EvalStep $step): float => $step->duration(),
            $this->steps,
        ));
    }

    #[Override]
    public function count(): int {
        return count($this->steps);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->steps;
    }

    // SERIALIZATION ///////////////////////////////////////////

    /** @return list<array<string, mixed>> */
    public function toArray(): array {
        return array_map(static fn (EvalStep $step): array => $step->toArray(), $this->steps);
    }

    /** @param list<array<string, mixed>> $data */
    public static function fromArray(array $data, ?EvalTracePolicy $policy = null): self {
        $steps = [];
        foreach ($data as $entry) {
            if (is_array($entry)) {
                $steps[] = EvalStep::fromArray($entry, $policy);
            }
        }
        return new self(...$steps);
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Evaluates continuation criteria for a given state.
 *
 * @template TState of object
 */
final readonly class ContinuationEvaluator
{
    /**
     * @var list<callable(TState): bool>
     */
    private array $criteria;

    /**
     * @param iterable<callable(TState): bool> $criteria
     * @psalm-suppress InvalidPropertyAssignmentValue, InvalidArgument - Template parameter passthrough
     */
    private function __construct(iterable $criteria) {
        $this->criteria = self::normalize($criteria);
    }

    /**
     * @template TNewState of object
     * @param callable(TNewState): bool ...$criteria
     * @return self<TNewState>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement
     */
    public static function with(callable ...$criteria): self {
        /** @var self<TNewState> */
        return new self($criteria);
    }

    /**
     * @template TNewState of object
     * @param iterable<callable(TNewState): bool> $criteria
     * @return self<TNewState>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement
     */
    public static function from(iterable $criteria): self {
        /** @var self<TNewState> */
        return new self($criteria);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->criteria === [];
    }

    /**
     * @param TState $state
     * @return bool
     */
    public function canContinue(object $state): bool {
        if ($this->criteria === []) {
            return false;
        }

        foreach ($this->criteria as $criterion) {
            if ($criterion($state) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable(TState): bool ...$criteria
     * @return self<TState>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement
     */
    public function withAdded(callable ...$criteria): self {
        if ($criteria === []) {
            return $this;
        }

        $merged = $this->criteria;
        foreach ($criteria as $criterion) {
            $merged[] = $criterion;
        }

        /** @var self<TState> */
        return new self($merged);
    }

    /**
     * @template TNormState of object
     * @param iterable<callable(TNormState): bool> $criteria
     * @return list<callable(TNormState): bool>
     */
    private static function normalize(iterable $criteria): array {
        $normalized = [];
        foreach ($criteria as $criterion) {
            $normalized[] = $criterion;
        }

        return $normalized;
    }
}

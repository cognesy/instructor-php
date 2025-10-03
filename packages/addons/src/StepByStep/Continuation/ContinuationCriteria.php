<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Shared base for domain continuation collections.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
readonly class ContinuationCriteria implements CanDecideToContinue
{
    /** @var list<CanDecideToContinue<TState>> */
    private array $criteria;
    /** @var ContinuationEvaluator<TState> */
    private ContinuationEvaluator $evaluator;

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     */
    public function __construct(CanDecideToContinue ...$criteria) {
        /** @var list<CanDecideToContinue<TState>> $criteria */
        $this->criteria = $criteria;
        /** @psalm-suppress InvalidPropertyAssignmentValue - Template parameter preserved through wrapAll */
        $this->evaluator = ContinuationEvaluator::from($this->wrapAll($criteria));
    }

    final public function isEmpty(): bool {
        return $this->evaluator->isEmpty();
    }

    /**
     * @param TState $state
     */
    #[\Override]
    final public function canContinue(object $state): bool {
        return $this->evaluator->canContinue($state);
    }

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     */
    final public function withCriteria(CanDecideToContinue ...$criteria): static {
        if ($criteria === []) {
            return $this;
        }

        return $this->newInstance(...array_merge($this->criteria, $criteria));
    }

    /**
     * @param list<CanDecideToContinue<TState>> $criteria
     * @return list<callable(TState): bool>
     */
    private function wrapAll(array $criteria): array {
        $wrapped = [];
        foreach ($criteria as $criterion) {
            $wrapped[] = $this->wrapCriterion($criterion);
        }

        return $wrapped;
    }

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     */
    protected function newInstance(CanDecideToContinue ...$criteria): static {
        return new static(...$criteria);
    }

    /**
     * @param CanDecideToContinue<TState> $criterion
     * @return callable(TState): bool
     */
    protected function wrapCriterion(CanDecideToContinue $criterion): callable {
        return static function(object $state) use ($criterion): bool {
            /** @var TState $state */
            return $criterion->canContinue($state);
        };
    }
}

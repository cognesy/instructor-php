<?php declare(strict_types=1);

namespace Cognesy\Addons\Core;

use Cognesy\Addons\Core\Contracts\CanDecideToContinue;
use Cognesy\Addons\Core\Contracts\Internal\CanProvideContinuationCriteria;

/**
 * Collection of continuation criteria with logical operations.
 * 
 * @template TState of object
 */
final readonly class ContinuationCriteria implements CanProvideContinuationCriteria
{
    /** @var CanDecideToContinue */
    private array $criteria;

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     */
    public function __construct(CanDecideToContinue ...$criteria) {
        $this->criteria = $criteria;
    }

    /**
     * Yield criteria that can apply to the given state.
     *
     * @param TState $state
     * @return CanDecideToContinue
     */
    public function nextContinuationCriterionFor(object $state): iterable {
        foreach ($this->criteria as $criterion) {
            if (!$criterion->canApply($state)) {
                continue;
            }
            yield $criterion;
        }
    }

    public function isEmpty(): bool {
        return $this->criteria === [];
    }

    public function count(): int {
        return count($this->criteria);
    }

    /**
     * Check if process can continue from current state.
     *
     * @param TState $state
     * @return bool
     */
    public function canContinue(object $state): bool {
        // no criteria means no continuation - to avoid infinite loops
        if ($this->isEmpty()) {
            return false;
        }
        foreach ($this->nextContinuationCriterionFor($state) as $criterion) {
            if (!$criterion->canContinue($state)) {
                return false;
            }
        }
        return true;
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Composable continuation criteria with AND/OR logic.
 *
 * Usage:
 *   // All must pass (AND logic) - default behavior
 *   $criteria = ContinuationCriteria::all(
 *       new StepsLimit(10),
 *       new TokenUsageLimit(16384),
 *   );
 *
 *   // Any can pass (OR logic)
 *   $criteria = ContinuationCriteria::any(
 *       new ToolCallPresenceCheck(...),
 *       new SelfCriticContinuationCheck(...),
 *   );
 *
 *   // Nested composition
 *   $criteria = ContinuationCriteria::all(
 *       new StepsLimit(10),
 *       new TokenUsageLimit(16384),
 *       ContinuationCriteria::any(
 *           new ToolCallPresenceCheck(...),
 *           new SelfCriticContinuationCheck(...),
 *       ),
 *   );
 *
 *   // Legacy usage (defaults to ALL mode)
 *   $criteria = new ContinuationCriteria(
 *       new StepsLimit(10),
 *       new TokenUsageLimit(16384),
 *   );
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
class ContinuationCriteria implements CanDecideToContinue
{
    public const MODE_ALL = 'all'; // AND - all must return true
    public const MODE_ANY = 'any'; // OR - any returning true is enough

    /** @var list<CanDecideToContinue<TState>> */
    protected array $criteria;
    protected string $mode;

    /**
     * Create criteria with AND logic (all must pass).
     * This is the default/legacy behavior for backwards compatibility.
     *
     * @param CanDecideToContinue<TState> ...$criteria
     */
    public function __construct(CanDecideToContinue ...$criteria) {
        $this->mode = self::MODE_ALL;
        $this->criteria = $criteria;
    }

    /**
     * All criteria must return true to continue (AND logic).
     *
     * @template T of object
     * @param CanDecideToContinue<T> ...$criteria
     * @return self<T>
     */
    public static function all(CanDecideToContinue ...$criteria): self {
        return new self(...$criteria);
    }

    /**
     * Any criterion returning true allows continuation (OR logic).
     *
     * @template T of object
     * @param CanDecideToContinue<T> ...$criteria
     * @return self<T>
     */
    public static function any(CanDecideToContinue ...$criteria): self {
        $instance = new self(...$criteria);
        $instance->mode = self::MODE_ANY;
        return $instance;
    }

    /**
     * Legacy factory - defaults to ALL mode.
     *
     * @param CanDecideToContinue<TState> ...$criteria
     * @return self<TState>
     */
    public static function from(CanDecideToContinue ...$criteria): self {
        return new self(...$criteria);
    }

    final public function isEmpty(): bool {
        return $this->criteria === [];
    }

    /**
     * @param TState $state
     */
    #[\Override]
    final public function canContinue(object $state): bool {
        if ($this->criteria === []) {
            return false;
        }

        return match ($this->mode) {
            self::MODE_ALL => $this->evaluateAll($state),
            self::MODE_ANY => $this->evaluateAny($state),
            default => false,
        };
    }

    /**
     * @param CanDecideToContinue<TState> ...$criteria
     */
    final public function withCriteria(CanDecideToContinue ...$criteria): static {
        if ($criteria === []) {
            return $this;
        }

        $instance = new static(...array_merge($this->criteria, $criteria));
        $instance->mode = $this->mode;
        return $instance;
    }

    /**
     * AND logic - all criteria must return true.
     *
     * @param TState $state
     */
    private function evaluateAll(object $state): bool {
        foreach ($this->criteria as $criterion) {
            if ($criterion->canContinue($state) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * OR logic - any criterion returning true is enough.
     *
     * @param TState $state
     */
    private function evaluateAny(object $state): bool {
        foreach ($this->criteria as $criterion) {
            if ($criterion->canContinue($state) === true) {
                return true;
            }
        }
        return false;
    }
}

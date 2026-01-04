<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Composable continuation criteria with logical operators.
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
 *   // None must pass (NOR logic)
 *   $criteria = ContinuationCriteria::none(
 *       new ErrorDetectedCheck(...),
 *       new UserCancelledCheck(...),
 *   );
 *
 *   // Negate a criterion
 *   $criteria = ContinuationCriteria::not(new SomeCheck(...));
 *
 *   // Custom predicate
 *   $criteria = ContinuationCriteria::when(
 *       fn($state) => $state->stepCount() < 10 && $state->hasToolCalls()
 *   );
 *
 *   // Nested composition
 *   $criteria = ContinuationCriteria::all(
 *       new StepsLimit(10),
 *       ContinuationCriteria::any(
 *           new ToolCallPresenceCheck(...),
 *           ContinuationCriteria::not(new ApprovedCheck(...)),
 *       ),
 *   );
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
class ContinuationCriteria implements CanDecideToContinue
{
    public const MODE_ALL = 'all';   // AND - all must return true
    public const MODE_ANY = 'any';   // OR - any returning true is enough
    public const MODE_NONE = 'none'; // NOR - none must return true
    public const MODE_NOT = 'not';   // NOT - negate single criterion
    public const MODE_WHEN = 'when'; // Custom predicate callback

    /** @var list<CanDecideToContinue<TState>> */
    protected array $criteria;
    protected string $mode;
    /** @var null|callable(TState): bool */
    protected $predicate = null;

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
     * None of the criteria must return true (NOR logic).
     * Continues only if ALL criteria return false.
     *
     * @template T of object
     * @param CanDecideToContinue<T> ...$criteria
     * @return self<T>
     */
    public static function none(CanDecideToContinue ...$criteria): self {
        $instance = new self(...$criteria);
        $instance->mode = self::MODE_NONE;
        return $instance;
    }

    /**
     * Negate a single criterion (NOT logic).
     *
     * @template T of object
     * @param CanDecideToContinue<T> $criterion
     * @return self<T>
     */
    public static function not(CanDecideToContinue $criterion): self {
        $instance = new self($criterion);
        $instance->mode = self::MODE_NOT;
        return $instance;
    }

    /**
     * Custom predicate callback for complex conditions.
     *
     * @template T of object
     * @param callable(T): bool $predicate
     * @return self<T>
     */
    public static function when(callable $predicate): self {
        $instance = new self();
        $instance->mode = self::MODE_WHEN;
        $instance->predicate = $predicate;
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
        return match ($this->mode) {
            self::MODE_ALL => $this->criteria === [] ? false : $this->evaluateAll($state),
            self::MODE_ANY => $this->criteria === [] ? false : $this->evaluateAny($state),
            self::MODE_NONE => $this->criteria === [] ? true : $this->evaluateNone($state),
            self::MODE_NOT => $this->criteria === [] ? false : $this->evaluateNot($state),
            self::MODE_WHEN => $this->predicate !== null && ($this->predicate)($state),
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

    /**
     * NOR logic - none of the criteria must return true.
     *
     * @param TState $state
     */
    private function evaluateNone(object $state): bool {
        foreach ($this->criteria as $criterion) {
            if ($criterion->canContinue($state) === true) {
                return false;
            }
        }
        return true;
    }

    /**
     * NOT logic - negate single criterion.
     *
     * @param TState $state
     */
    private function evaluateNot(object $state): bool {
        return !$this->criteria[0]->canContinue($state);
    }
}

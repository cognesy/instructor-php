<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use BackedEnum;
use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stops when the current step's finish reason matches a configured set.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class FinishReasonCheck implements CanDecideToContinue
{
    /** @var Closure(TState): mixed */
    private Closure $finishReasonResolver;
    /** @var list<string> */
    private array $normalizedStopReasons;

    /**
     * @param array<int, string|int|BackedEnum|null> $stopReasons
     * @param callable(TState): (string|int|BackedEnum|null) $finishReasonResolver
     */
    public function __construct(
        array $stopReasons,
        callable $finishReasonResolver,
    ) {
        $this->finishReasonResolver = Closure::fromCallable($finishReasonResolver);
        $this->normalizedStopReasons = $this->normalizeStopReasons($stopReasons);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function canContinue(object $state): bool {
        if ($this->normalizedStopReasons === []) {
            return true;
        }

        /** @var TState $state */
        $reason = ($this->finishReasonResolver)($state);
        if ($reason instanceof BackedEnum) {
            $reason = $reason->value;
        }
        if ($reason === null) {
            return true;
        }

        return !in_array($reason, $this->normalizedStopReasons, true);
    }

    /**
     * @param array<int, string|int|BackedEnum|null> $stopReasons
     * @return list<string>
     */
    private function normalizeStopReasons(array $stopReasons): array {
        $normalized = [];
        foreach ($stopReasons as $reason) {
            if ($reason instanceof BackedEnum) {
                $normalized[] = (string) $reason->value;
                continue;
            }
            if ($reason === null) {
                continue;
            }
            $normalized[] = (string) $reason;
        }

        return $normalized;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Continuation\Criteria;

use BackedEnum;
use Closure;
use Cognesy\Addons\Core\Continuation\CanDecideToContinue;

/**
 * Stops when the current step's finish reason matches a configured set.
 *
 * @template TState of object
 */
final readonly class FinishReasonCheck implements CanDecideToContinue
{
    /** @var Closure(TState): mixed */
    private Closure $finishReasonResolver;
    /** @var list<int|string|null> */
    private array $normalizedStopReasons;

    /**
     * @param array<int, string|int|BackedEnum|null> $stopReasons
     * @param Closure(TState): string|int|BackedEnum|null $finishReasonResolver
     */
    public function __construct(
        private array $stopReasons,
        callable $finishReasonResolver,
    ) {
        $this->finishReasonResolver = Closure::fromCallable($finishReasonResolver);
        $this->normalizedStopReasons = $this->normalizeStopReasons($stopReasons);
    }

    public function canContinue(object $state): bool {
        if ($this->normalizedStopReasons === []) {
            return true;
        }

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
     * @return list<int|string|null>
     */
    private function normalizeStopReasons(array $stopReasons): array {
        $normalized = [];
        foreach ($stopReasons as $reason) {
            if ($reason instanceof BackedEnum) {
                $normalized[] = $reason->value;
                continue;
            }
            $normalized[] = $reason;
        }

        return $normalized;
    }
}

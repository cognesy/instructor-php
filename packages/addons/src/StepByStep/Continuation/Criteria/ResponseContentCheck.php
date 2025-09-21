<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Uses a predicate to decide whether to continue based on the latest response content.
 *
 * @template TState of object
 * @template TResponse of object|null
 */
final readonly class ResponseContentCheck implements CanDecideToContinue
{
    /** @var Closure(TState): TResponse */
    private Closure $responseResolver;
    /** @var Closure(TResponse): bool */
    private Closure $predicate;

    /**
     * @param Closure(TState): TResponse $responseResolver
     * @param Closure(TResponse): bool $predicate Returns true to continue, false to stop
     */
    public function __construct(callable $responseResolver, callable $predicate) {
        $this->responseResolver = Closure::fromCallable($responseResolver);
        $this->predicate = Closure::fromCallable($predicate);
    }

    public function canContinue(object $state): bool {
        $response = ($this->responseResolver)($state);
        if ($response === null) {
            return true;
        }

        return ($this->predicate)($response);
    }
}

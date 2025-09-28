<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Operators;

use Closure;
use Cognesy\Experimental\Pipeline2\Contracts\Continuation;
use Cognesy\Experimental\Pipeline2\Contracts\Operator;

/**
 * A generic operator that allows for "around" logic via a closure,
 * perfectly demonstrating the middleware pattern.
 */
readonly final class Around implements Operator
{
    /** @var callable(mixed, Continuation):mixed */
    private Closure $closure;

    public function __construct(callable $closure) {
        $this->closure = $closure;
    }

    public function supports(mixed $payload): bool {
        return true;
    }

    public function handle(mixed $payload, Continuation $next): mixed {
        return ($this->closure)($payload, $next);
    }
}

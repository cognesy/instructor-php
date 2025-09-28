<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Operators;

use Closure;
use Cognesy\Experimental\Pipeline\Contracts\Next;
use Cognesy\Experimental\Pipeline\Contracts\Operator;

/**
 * A generic operator that allows for "around" logic via a closure,
 * perfectly demonstrating the middleware pattern.
 */
readonly final class Around implements Operator
{
    /** @var callable(mixed, Next):mixed */
    private Closure $closure;

    public function __construct(callable $closure) {
        $this->closure = $closure;
    }

    public function supports(mixed $payload): bool {
        return true;
    }

    public function handle(mixed $payload, Next $next): mixed {
        return ($this->closure)($payload, $next);
    }
}

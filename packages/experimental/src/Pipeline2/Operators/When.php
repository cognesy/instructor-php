<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Operators;

use Closure;
use Cognesy\Experimental\Pipeline2\Contracts\Continuation;
use Cognesy\Experimental\Pipeline2\Contracts\Operator;

readonly final class When implements Operator
{
    /** @var Closure(mixed):bool */
    private Closure $condition;
    private Closure $callable;

    public function __construct(callable $condition, callable $callable) {
        $this->condition = $condition;
        $this->callable = $callable;
    }

    public function supports(mixed $payload): bool {
        return true;
    }

    public function handle(mixed $payload, Continuation $next): mixed {
        if (($this->condition)($payload)) {
            return $next->handle(($this->callable)($payload));
        }
        return $next->handle($payload);
    }
}
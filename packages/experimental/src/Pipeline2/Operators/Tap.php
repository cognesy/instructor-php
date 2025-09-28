<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Operators;

use Closure;
use Cognesy\Experimental\Pipeline2\Contracts\Continuation;
use Cognesy\Experimental\Pipeline2\Contracts\Operator;

readonly final class Tap implements Operator
{
    /** @var callable(mixed):void */
    private Closure $sideEffect;

    public function __construct(callable $sideEffect) {
        $this->sideEffect = $sideEffect;
    }

    public function supports(mixed $payload): bool {
        return true;
    }

    public function handle(mixed $payload, Continuation $next): mixed {
        ($this->sideEffect)($payload);
        return $next->handle($payload);
    }
}
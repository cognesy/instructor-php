<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Operators;

use Closure;
use Cognesy\Experimental\Pipeline2\Contracts\Continuation;
use Cognesy\Experimental\Pipeline2\Contracts\Operator;

/**
 * An operator that transforms the payload using a callable.
 */
readonly final class Map implements Operator
{
    /** @var callable(mixed):mixed */
    private Closure $mapper;

    public function __construct(callable $mapper) {
        $this->mapper = $mapper;
    }

    #[\Override]
    public function supports(mixed $payload): bool {
        return true;
    }

    #[\Override]
    public function handle(mixed $payload, Continuation $next): mixed {
        $mappedPayload = ($this->mapper)($payload);
        return $next->handle($mappedPayload);
    }
}

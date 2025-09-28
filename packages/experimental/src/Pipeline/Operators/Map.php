<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Operators;

use Closure;
use Cognesy\Experimental\Pipeline\Contracts\Next;
use Cognesy\Experimental\Pipeline\Contracts\Operator;

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

    public function supports(mixed $payload): bool {
        return true;
    }

    public function handle(mixed $payload, Next $next): mixed {
        $mappedPayload = ($this->mapper)($payload);
        return $next->handle($mappedPayload);
    }
}

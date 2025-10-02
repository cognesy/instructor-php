<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Actions;

use Cognesy\Experimental\Pipeline2\Contracts\CanProcessPayload;
use Cognesy\Experimental\Pipeline2\Contracts\Operator;

/**
 * An invokable class that wraps a single pipeline operator,
 * handling the creation of the NextCallHandler.
 */
class ExecuteOperatorCall implements CanProcessPayload
{
    public function __construct(
        private Operator $operator,
        private CanProcessPayload $downstreamFn,
        private int $index,
        private HandleBoundary $atBoundaryFn,
    ) {}

    #[\Override]
    public function __invoke(mixed $payload): mixed {
        $next = ExecuteCall::next(
            nextFn: $this->downstreamFn,
            index: $this->index,
            atBoundaryFn: $this->atBoundaryFn,
        );
        return ($this->operator)($payload, $next);
    }
}

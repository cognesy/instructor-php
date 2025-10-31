<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Core;

use Cognesy\Experimental\Interpreter\Contracts\Program;
use Cognesy\Experimental\Interpreter\Contracts\CanMakeNextStep;
use Cognesy\Experimental\Interpreter\InterpreterState;

/**
 * SequencedComputation
 * Represents "do A, then depending on A's value, build and do B".
 */
final readonly class InterpretableSequence implements Program
{
    public function __construct(
        private Program         $current,
        private CanMakeNextStep $next,
    ) {}

     #[\Override]
    public function then(CanMakeNextStep $next): Program {
        return new InterpretableSequence(
            current: $this,
            next: $next,
        );
    }

    // ACTIONS ////////////////////////////////////////////////

    #[\Override]
    public function __invoke(InterpreterState $state): InterpreterState {
        $newState = ($this->current)($state);
        if ($newState->isError) {
            return $newState;
        }

        $next = ($this->next)($newState);
        return $next($newState);
    }
}

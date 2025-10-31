<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Core;

use Cognesy\Experimental\Interpreter\Contracts\Program;
use Cognesy\Experimental\Interpreter\Contracts\CanMakeNextStep;
use Cognesy\Experimental\Interpreter\InterpreterState;

final readonly class FailedProgram implements Program
{
    public function __construct(
        private string $errorMessage
    ) {}

    #[\Override]
    public function then(CanMakeNextStep $next): Program {
        return $this; // short-circuit on failure
    }

    // ACTIONS ////////////////////////////////////////////////

    #[\Override]
    public function __invoke(InterpreterState $state): InterpreterState {
        return InterpreterState::failed(
            context: $state->context,
            errorMessage: $this->errorMessage,
        );
    }
}

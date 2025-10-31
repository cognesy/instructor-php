<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Core;

use Cognesy\Experimental\Interpreter\Contracts\Program;
use Cognesy\Experimental\Interpreter\Contracts\CanMakeNextStep;
use Cognesy\Experimental\Interpreter\Contracts\CanBeInterpreted;
use Cognesy\Experimental\Interpreter\InterpreterState;

class Instruction implements Program
{
    public function __construct(
        private readonly CanBeInterpreted $instruction
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
        return ($this->instruction)($state);
    }
}
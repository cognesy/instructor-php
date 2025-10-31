<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Contracts;

use Cognesy\Experimental\Interpreter\InterpreterState;

/**
 * Conditionally defines how to execute further steps in a program.
 * Given the value produced so far, decide what computation should happen next.
 *
 * Use cases: if-then-else, while, match, etc.
 */
interface CanMakeNextStep
{
    public function __invoke(InterpreterState $state): Program;
}

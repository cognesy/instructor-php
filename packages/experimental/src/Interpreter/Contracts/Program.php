<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Contracts;

use Cognesy\Experimental\Interpreter\InterpreterContext;
use Cognesy\Experimental\Interpreter\InterpreterState;

/**
 * Composable building block of the interpreter.
 * 1) Can execute the step for given interpreter state and return new interpreter state.
 * 2) Can be chained with another step returning new composite Program depending on previous step.
 */
interface Program extends CanBeInterpreted
{
    public function then(CanMakeNextStep $next): Program;
}

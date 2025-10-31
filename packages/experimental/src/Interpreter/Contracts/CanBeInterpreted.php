<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Contracts;

use Cognesy\Experimental\Interpreter\InterpreterState;

interface CanBeInterpreted
{
    public function __invoke(InterpreterState $state) : InterpreterState;
}
<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Ops;

use Cognesy\Experimental\Interpreter\Contracts\CanBeInterpreted;
use Cognesy\Experimental\Interpreter\Core\Instruction;
use Cognesy\Experimental\Interpreter\InterpreterState;

final class GetVar extends Instruction
{
    public function __construct(string $name) {
        parent::__construct(new GetVarOp($name));
    }
}

class GetVarOp implements CanBeInterpreted {
    public function __construct(
        private string $name
    ) {}

    public function __invoke(InterpreterState $state): InterpreterState {
        if (!array_key_exists($this->name, $state->context->environment)) {
            return $state->withError("Undefined variable '{$this->name}'");
        }
        return $state->withValue($state->context->environment[$this->name]);
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Ops;

use Cognesy\Experimental\Interpreter\Contracts\CanBeInterpreted;
use Cognesy\Experimental\Interpreter\Core\Instruction;
use Cognesy\Experimental\Interpreter\InterpreterState;

final class SetVar extends Instruction
{
    public function __construct(
        string $name,
        mixed $value
    ) {
        parent::__construct(new SetVarOp($name, $value));
    }
}

final readonly class SetVarOp implements CanBeInterpreted
{
    public function __construct(
        private string $name,
        private mixed $value
    ) {}

    public function __invoke(InterpreterState $state): InterpreterState {
        $newContext = $state->context->withEnvironment(
            [...$state->context->environment, $this->name => $this->value],
        );

        return $state->withContext($newContext);
    }
}
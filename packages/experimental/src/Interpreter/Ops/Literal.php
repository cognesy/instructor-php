<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Ops;

use Cognesy\Experimental\Interpreter\Contracts\CanBeInterpreted;
use Cognesy\Experimental\Interpreter\Core\Instruction;
use Cognesy\Experimental\Interpreter\InterpreterState;

final class Literal extends Instruction
{
    public function __construct(mixed $value) {
        parent::__construct(new LiteralOp($value));
    }
}

final readonly class LiteralOp implements CanBeInterpreted
{
    public function __construct(private mixed $value) {}

    public function __invoke(InterpreterState $state): InterpreterState {
        return $state->withValue($this->value);
    }
}
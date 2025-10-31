<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Ops;

use Cognesy\Experimental\Interpreter\Contracts\CanBeInterpreted;
use Cognesy\Experimental\Interpreter\Core\Instruction;
use Cognesy\Experimental\Interpreter\InterpreterState;

/**
 * ReturnConstantComputation
 * Produces a plain value, does not modify context.
 */
final class ReturnValue extends Instruction
{
    public function __construct(mixed $constant) {
        parent::__construct(new ReturnValueOp($constant));
    }
}

final readonly class ReturnValueOp implements CanBeInterpreted
{
    public function __construct(
        private mixed $constant
    ) {}

    public function __invoke(InterpreterState $state): InterpreterState {
        return $state->withValue($this->constant);
    }
}
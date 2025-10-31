<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Ops;

use Cognesy\Experimental\Interpreter\Contracts\CanBeInterpreted;
use Cognesy\Experimental\Interpreter\Core\Instruction;
use Cognesy\Experimental\Interpreter\Enums\BinaryOperatorType;
use Cognesy\Experimental\Interpreter\InterpreterState;

final class BinaryOperation extends Instruction
{
    public function __construct(
        BinaryOperatorType $opType,
        mixed              $leftVal,
        mixed              $rightVal,
    ) {
        parent::__construct(new BinaryOperationOp($opType, $leftVal, $rightVal));
    }
}

final readonly class BinaryOperationOp implements CanBeInterpreted {
    public function __construct(
        private BinaryOperatorType $opType,
        private mixed              $leftVal,
        private mixed              $rightVal,
    ) {}

    public function __invoke(InterpreterState $state): InterpreterState {
        $result = match ($this->opType) {
            BinaryOperatorType::Add => $this->leftVal + $this->rightVal,
            BinaryOperatorType::Sub => $this->leftVal - $this->rightVal,
            BinaryOperatorType::Mul => $this->leftVal * $this->rightVal,
            BinaryOperatorType::Div => $this->leftVal / $this->rightVal,
            BinaryOperatorType::Gt  => $this->leftVal > $this->rightVal,
            BinaryOperatorType::Lt  => $this->leftVal < $this->rightVal,
            BinaryOperatorType::Eq  => $this->leftVal === $this->rightVal,
            BinaryOperatorType::Neq  => $this->leftVal !== $this->rightVal,
            BinaryOperatorType::Gte => $this->leftVal >= $this->rightVal,
            BinaryOperatorType::Lte => $this->leftVal <= $this->rightVal,
        };

        return $state->withValue($result);
    }
}
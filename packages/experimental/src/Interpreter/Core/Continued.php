<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Core;

use Cognesy\Experimental\Interpreter\Contracts\CanMakeNextStep;
use Cognesy\Experimental\Interpreter\Contracts\Program;
use Cognesy\Experimental\Interpreter\InterpreterState;

class Continued implements CanMakeNextStep
{
    private \Closure $nextFn;

    private function __construct(\Closure $nextFn) {
        $this->nextFn = $nextFn;
    }

    // CONSTRUCTORS ///////////////////////////////////////////

    public static function with(\Closure $nextFn) : self {
        return new self($nextFn);
    }

    public static function withIgnoredOutput(Program $next) : self {
        return new self(fn(mixed $_) => $next);
    }

    // ACTIONS ////////////////////////////////////////////////

    public function __invoke(InterpreterState $state): Program {
        return ($this->nextFn)($state);
    }
}
<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Core\Data\Request;
use Cognesy\Instructor\Enums\Mode;

class FunctionCallerFactory
{
    public function __construct(
        private array $modeHandlers = [],
        private ?Mode $forceMode = null,
    ) {}

    public function fromRequest(Request $request) : CanCallFunction {
        $mode = $request->mode->value;
        // if mode is forced, use it
        if ($this->forceMode) {
            $mode = $this->forceMode->value;
        }
        // check if handler for mode exists
        if (!isset($this->modeHandlers[$mode])) {
            throw new \Exception("Mode handler not found for mode: {$mode}");
        }
        // instantiate handler via provided callback
        $callback = $this->modeHandlers[$mode];
        return $callback();
    }
}
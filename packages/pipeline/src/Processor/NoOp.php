<?php

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Do nothing, just pass the state through.
 */
readonly class NoOp implements CanProcessState {
    public function process(ProcessingState $state): ProcessingState {
        return $state;
    }
}
<?php

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Process the state without modification.
 */
readonly class PassThrough implements CanProcessState {
    public function process(ProcessingState $state): ProcessingState {
        return $state;
    }
}
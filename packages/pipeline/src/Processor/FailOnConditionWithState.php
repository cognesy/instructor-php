<?php

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use RuntimeException;

readonly class FailOnConditionWithState implements CanProcessState {
    /**
     * @param Closure(ProcessingState):bool $condition
     */
    public function __construct(
        private Closure $condition,
        private string $message = 'Processing failed due to specified condition.',
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        return match(true) {
            !($this->condition)($state) => $state->failWith(new RuntimeException($this->message)),
            default => $state,
        };
    }
}
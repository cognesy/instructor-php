<?php

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Tap processor that allows you to perform side effects on the state
 * without modifying it.
 *
 * This is useful for logging, debugging, or any other side effects
 * that do not change the state.
 */
readonly class Tap implements CanProcessState {
    private function __construct(
        private CanProcessState $processor,
    ) {}

    public static function with(CanProcessState $processor): self {
        return new self($processor);
    }

    public function process(ProcessingState $state): ProcessingState {
        $this->processor->process($state);
        return $state;
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\ProcessingState;

/**
 * Processor that conditionally executes another processor based on processing state.
 *
 * Handles the Pipeline::when() functionality.
 */
class ConditionalValueProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Closure $condition,
        private readonly ProcessorInterface $processor,
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        return match(true) {
            ($this->condition)($state->value()) => $this->processor->process($state),
            default => $state,
        };
    }
}
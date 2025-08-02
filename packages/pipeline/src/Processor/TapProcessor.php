<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\ProcessingState;

/**
 * Processor that executes a side-effect processor without modifying the state.
 *
 * Handles the Pipeline::tap() functionality.
 */
class TapProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $processor
    ) {}

    public function process(ProcessingState $state): ProcessingState
    {
        $this->processor->process($state);
        return $state;
    }
}
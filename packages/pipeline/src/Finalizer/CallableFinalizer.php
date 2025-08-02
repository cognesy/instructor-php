<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Finalizer;

use Closure;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

/**
 * Wrapper that adapts callable functions to FinalizerInterface.
 *
 * Always calls the finalizer with the Result object (most common case).
 * For other parameter types, users should implement FinalizerInterface directly.
 *
 * This allows simple finalizers to be defined as closures without needing a full class.
 */
class CallableFinalizer implements FinalizerInterface
{
    /**
     * Creates a finalizer that wraps a callable function.
     *
     * This is useful for simple finalizers that just need to process the Result.
     *
     * @param Closure<Result, mixed> $finalizer Function that takes a Result and returns the final value.
     */
    public function __construct(
        private readonly Closure $finalizer,
    ) {}

    public function finalize(ProcessingState $state): mixed {
        return ($this->finalizer)($state->result());
    }
}
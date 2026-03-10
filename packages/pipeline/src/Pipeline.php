<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Throwable;

final class Pipeline
{
    /**
     * @param list<Closure(CanCarryState):CanCarryState> $steps
     * @param list<Closure(CanCarryState):void> $failureHandlers
     * @param list<Closure(CanCarryState):CanCarryState> $finalizers
     */
    public function __construct(
        private array $steps = [],
        private array $failureHandlers = [],
        private array $finalizers = [],
        private ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure,
    ) {}

    public static function builder(ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure): PipelineBuilder {
        return new PipelineBuilder($onError);
    }

    public function executeWith(CanCarryState $state): PendingExecution {
        return new PendingExecution(initialState: $state, pipeline: $this);
    }

    public function process(CanCarryState $state): CanCarryState {
        $currentState = $state;

        if ($currentState->isFailure()) {
            $this->runFailureHandlers($currentState);
        }

        foreach ($this->steps as $step) {
            if ($currentState->isFailure()) {
                break;
            }

            $currentState = $this->executeStep($step, $currentState);

            if ($currentState->isFailure()) {
                $this->runFailureHandlers($currentState);
            }
        }

        return $this->runFinalizers($currentState);
    }

    private function executeStep(Closure $step, CanCarryState $state): CanCarryState {
        try {
            return $step($state);
        } catch (Throwable $e) {
            return match ($this->onError) {
                ErrorStrategy::FailFast => throw $e,
                ErrorStrategy::ContinueWithFailure => $state->failWith($e),
            };
        }
    }

    private function runFailureHandlers(CanCarryState $state): void {
        foreach ($this->failureHandlers as $handler) {
            try {
                $handler($state);
            } catch (Throwable $e) {
                if ($this->onError === ErrorStrategy::FailFast) {
                    throw $e;
                }
            }
        }
    }

    private function runFinalizers(CanCarryState $state): CanCarryState {
        $currentState = $state;

        foreach ($this->finalizers as $finalizer) {
            $currentState = $this->executeStep($finalizer, $currentState);

            if ($currentState->isFailure()) {
                break;
            }
        }

        return $currentState;
    }
}

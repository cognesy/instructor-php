<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Internal\OperatorStack;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Exception;

/**
 * Pipeline with per-execution & per-step middleware support.
 */
class Pipeline implements CanProcessState
{
    private OperatorStack $steps;
    private OperatorStack $middleware; // per-pipeline execution middleware stack
    private OperatorStack $hooks; // per-step execution hooks
    private OperatorStack $finalizers; // per-pipeline execution finalizers, regardless of success or failure
    private ErrorStrategy $onError;

    public function __construct(
        ?OperatorStack $steps = null,
        ?OperatorStack $middleware = null,
        ?OperatorStack $hooks = null,
        ?OperatorStack $finalizers = null,
        ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure,
    ) {
        $this->steps = $steps ?? new OperatorStack();
        $this->finalizers = $finalizers ?? new OperatorStack();
        $this->middleware = $middleware ?? new OperatorStack();
        $this->hooks = $hooks ?? new OperatorStack();
        $this->onError = $onError;
    }

    // STATIC FACTORY METHODS ////////////////////////////////////////////////////////////////

    public static function builder(ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure): PipelineBuilder {
        return new PipelineBuilder($onError);
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function executeWith(CanCarryState $state): PendingExecution {
        return new PendingExecution(initialState: $state, pipeline: $this);
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $processedState = match (true) {
            ($this->middleware->isEmpty() && $this->hooks->isEmpty()) => $this->processStack($state, $this->steps),
            default => $this->applyStepsWithMiddleware($state, $this->middleware, $this->steps, $this->hooks),
        };
        $output = $this->processStack($processedState, $this->finalizers);
        return $next ? $next($output) : $output;
    }

    // INTERNAL IMPLEMENTATION ///////////////////////////////////////////////////////////////

    private function applyStepsWithMiddleware(
        CanCarryState $state,
        OperatorStack $middleware,
        OperatorStack $steps,
        OperatorStack $hooks,
    ): CanCarryState {
        return match (true) {
            $middleware->isEmpty() => $this->applySteps($state, $steps, $hooks),
            default => $this->tryProcess(
                $middleware->callStack(fn($comp) => $this->applySteps($comp, $steps, $hooks)),
                $state
            ),
        };
    }

    private function applySteps(
        CanCarryState $state,
        OperatorStack $steps,
        OperatorStack $hooks,
    ): CanCarryState {
        $currentState = $state;
        foreach ($steps->getIterator() as $step) {
            $nextState = match (true) {
                $hooks->isEmpty() => $this->tryProcess($step, $currentState),
                default => $this->executeStepWithHooks($step, $currentState, $hooks),
            };
            if (!$this->shouldContinueProcessing($nextState)) {
                return $nextState;
            }
            $currentState = $nextState;
        }
        return $currentState;
    }

    private function executeStepWithHooks(
        CanProcessState $step,
        CanCarryState $state,
        OperatorStack $hooks,
    ): CanCarryState {
        $stack = $hooks->callStack(function (CanCarryState $state) use ($step) {
            return match (true) {
                !$this->shouldContinueProcessing($state) => $state,
                default => $this->tryProcess($step, $state),
            };
        });
        return $this->tryProcess($stack, $state);
    }

    private function processStack(
        CanCarryState $state,
        OperatorStack $operators,
    ): CanCarryState {
        $currentState = $state;
        foreach ($operators->getIterator() as $step) {
            $nextState = $this->tryProcess($step, $currentState);
            if (!$this->shouldContinueProcessing($nextState)) {
                return $nextState;
            }
            $currentState = $nextState;
        }
        return $currentState;
    }

    private function shouldContinueProcessing(CanCarryState $state): bool {
        return $state->result()->isSuccess();
    }

    private function tryProcess(
        CanProcessState|callable $processable,
        CanCarryState $state
    ): CanCarryState {
        try {
            return match(true) {
                $processable instanceof CanProcessState => $processable->process($state),
                default => $processable($state),
            };
        } catch (Exception $e) {
            if ($this->onError === ErrorStrategy::FailFast) {
                throw $e;
            }
            return $state->failWith($e);
        }
    }
}

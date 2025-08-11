<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Internal\OperatorStack;
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
    private ErrorStrategy $errorStrategy;

    public function __construct(
        ?OperatorStack $steps = null,
        ?OperatorStack $middleware = null,
        ?OperatorStack $hooks = null,
        ?OperatorStack $finalizers = null,
        ErrorStrategy $errorStrategy = ErrorStrategy::Continue,
    ) {
        $this->steps = $steps ?? new OperatorStack();
        $this->finalizers = $finalizers ?? new OperatorStack();
        $this->middleware = $middleware ?? new OperatorStack();
        $this->hooks = $hooks ?? new OperatorStack();
        $this->errorStrategy = $errorStrategy;
    }

    // STATIC FACTORY METHODS ////////////////////////////////////////////////////////////////

    public static function builder(): PipelineBuilder {
        return new PipelineBuilder();
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function executeWith(mixed $initialValue = null, TagInterface ...$tags): PendingExecution {
        $initialState = ProcessingState::with($initialValue, $tags);
        return new PendingExecution($initialState, $this);
    }


    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $processedState = match (true) {
            ($this->middleware->isEmpty() && $this->hooks->isEmpty()) => $this->processStack($state, $this->steps),
            default => $this->applyStepsWithMiddleware($state, $this->middleware, $this->steps, $this->hooks),
        };
        $output = $this->processStack($processedState, $this->finalizers);
        return $next ? $next($output) : $output;
    }

    // INTERNAL IMPLEMENTATION ///////////////////////////////////////////////////////////////

    private function applyStepsWithMiddleware(
        ProcessingState $state,
        OperatorStack $middleware,
        OperatorStack $steps,
        OperatorStack $hooks,
    ): ProcessingState {
        return match (true) {
            $middleware->isEmpty() => $this->applySteps($state, $steps, $hooks),
            default => $this->tryProcess(
                $middleware->stack(fn($comp) => $this->applySteps($comp, $steps, $hooks)),
                $state
            ),
        };
    }

    private function applySteps(
        ProcessingState $state,
        OperatorStack $steps,
        OperatorStack $hooks,
    ): ProcessingState {
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
        ProcessingState $state,
        OperatorStack $hooks,
    ): ProcessingState {
        $stack = $hooks->stack(function (ProcessingState $state) use ($step) {
            return match (true) {
                !$this->shouldContinueProcessing($state) => $state,
                default => $this->tryProcess($step, $state),
            };
        });
        return $this->tryProcess($stack, $state);
    }

    private function processStack(
        ProcessingState $state,
        OperatorStack $steps,
    ): ProcessingState {
        $currentState = $state;
        foreach ($steps->getIterator() as $step) {
            $nextState = $this->tryProcess($step, $currentState);
            if (!$this->shouldContinueProcessing($nextState)) {
                return $nextState;
            }
            $currentState = $nextState;
        }
        return $currentState;
    }

    private function shouldContinueProcessing(ProcessingState $state): bool {
        return $state->result()->isSuccess();
    }

    private function tryProcess(CanProcessState|callable $processable, ProcessingState $state): ProcessingState {
        try {
            return match(true) {
                $processable instanceof CanProcessState => $processable->process($state),
                default => $processable($state),
            };
        } catch (Exception $e) {
            if ($this->errorStrategy === ErrorStrategy::FailFast) {
                throw $e;
            }
            return $state->failWith($e);
        }
    }
}

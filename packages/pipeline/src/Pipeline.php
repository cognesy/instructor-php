<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Finalizer\CallableFinalizer;
use Cognesy\Pipeline\Finalizer\FinalizerInterface;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareStack;
use Cognesy\Pipeline\Processor\ProcessorInterface;
use Cognesy\Pipeline\Processor\ProcessorStack;
use Exception;

/**
 * Pipeline with per-execution & per-step middleware support.
 */
class Pipeline implements CanProcessState
{
    use Traits\HandlesOutput;

    private ProcessorStack $processors;
    private FinalizerInterface $finalizer;
    private PipelineMiddlewareStack $middleware; // per-pipeline execution middleware stack
    private PipelineMiddlewareStack $hooks; // per-processor execution hooks

    public function __construct(
        ?ProcessorStack $processors = null,
        ?FinalizerInterface $finalizer = null,
        ?PipelineMiddlewareStack $middleware = null,
        ?PipelineMiddlewareStack $hooks = null,
    ) {
        $this->processors = $processors ?? new ProcessorStack();
        $this->finalizer = $finalizer ?? new CallableFinalizer(fn($data) => $data);
        $this->middleware = $middleware ?? new PipelineMiddlewareStack();
        $this->hooks = $hooks ?? new PipelineMiddlewareStack();
    }

    // STATIC FACTORY METHODS ////////////////////////////////////////////////////////////////

    public static function empty(): PipelineBuilder {
        return new PipelineBuilder();
    }

    public static function from(callable $source): PipelineBuilder {
        return new PipelineBuilder(source: $source);
    }

    public static function for(mixed $value): PipelineBuilder {
        return new PipelineBuilder(source: fn() => $value);
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function execute(ProcessingState $state) : ProcessingState {
        $processedState = match(true) {
            ($this->middleware->isEmpty() && $this->hooks->isEmpty()) => $this->applyOnlyProcessors($state),
            default => $this->applyProcessorsWithMiddleware($state),
        };
        return $this->applyFinalizer($this->finalizer, $processedState);
    }

    // INTERNAL IMPLEMENTATION ///////////////////////////////////////////////////////////////

    private function applyProcessorsWithMiddleware(ProcessingState $state): ProcessingState {
        return match(true) {
            $this->middleware->isEmpty() => $this->applyProcessors($state),
            default => $this->middleware->process($state, fn($comp) => $this->applyProcessors($comp))
        };
    }

    private function applyProcessors(ProcessingState $state): ProcessingState {
        $currentState = $state;
        foreach ($this->processors->getIterator() as $processor) {
            $nextState = match(true) {
                $this->hooks->isEmpty() => $this->executeProcessor($processor, $currentState),
                default => $this->executeProcessorWithHooks($processor, $currentState),
            };
            if (!$this->shouldContinueProcessing($nextState)) {
                return $nextState;
            }
            $currentState = $nextState;
        }
        return $currentState;
    }

    private function applyOnlyProcessors(ProcessingState $state): ProcessingState {
        $currentState = $state;
        foreach ($this->processors->getIterator() as $processor) {
            $nextState = $this->executeProcessor($processor, $currentState);
            if (!$this->shouldContinueProcessing($nextState)) {
                return $nextState;
            }
            $currentState = $nextState;
        }
        return $currentState;
    }

    private function executeProcessorWithHooks(ProcessorInterface $processor, ProcessingState $state): ProcessingState {
        return $this->hooks->process($state, function (ProcessingState $state) use ($processor) {
            return match(true) {
                !$this->shouldContinueProcessing($state) => $state,
                default => $this->executeProcessor($processor, $state),
            };
        });
    }

    private function executeProcessor(ProcessorInterface $processor, ProcessingState $state): ProcessingState {
        try {
            return $processor->process($state);
        } catch (Exception $e) {
            return $this->createFailureState($state, $e);
        }
    }

    private function applyFinalizer(FinalizerInterface $finalizer, ProcessingState $state): ProcessingState {
        try {
            return $this->asProcessingState($finalizer->finalize($state), $state);
        } catch (Exception $e) {
            return $this->createFailureState($state, $e);
        }
    }

    private function shouldContinueProcessing(ProcessingState $state): bool {
        return $state->result()->isSuccess();
    }
}

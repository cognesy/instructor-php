<?php declare(strict_types=1);

namespace Cognesy\Stream;

use Cognesy\Stream\Contracts\Stream;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Sinks\SideEffect\ToQueueReducer;
use Iterator;
use SplQueue;

/**
 * @implements Stream<int, mixed>
 */
final class TransformationStream implements Stream
{
    /** @var iterable<mixed> */
    private readonly iterable $input;
    private readonly Transformation $transformation;

    private ?Iterator $iterator;
    private ?TransformationExecution $execution;
    private ?SplQueue $queue = null;

    public function __construct(
        ?iterable $input,
        ?Transformation $transformation,
        ?TransformationExecution $execution,
        ?Iterator $iterator,
    ) {
        $this->input = $input ?? [];
        $this->transformation = $transformation;
        $this->execution = $execution;
        $this->iterator = $iterator;
    }

    public static function from(iterable $input): self {
        return new self(
            input: $input,
            transformation: new Transformation,
            execution: null,
            iterator: null,
        );
    }

    // MUTATORS ////////////////////////////////////////////////////////////

    public function using(Transformation $transformation) : self {
        return $this->with(transformation: $transformation);
    }

    public function through(Transducer ...$transducer): self {
        return $this->with(transformation: $this->transformation->through(...$transducer));
    }

    // ACCESSORS ///////////////////////////////////////////////////////////

    #[\Override]
    public function getIterator(): Iterator {
        if ($this->iterator === null) {
            $this->iterator = $this->makeIterator($this->execution());
        }
        return $this->iterator;
    }

    public function getCompleted() : mixed {
        return $this->execution()->completed();
    }

    // INTERNAL /////////////////////////////////////////////////////////////

    private function with(
        ?iterable $input = null,
        ?Transformation $transformation = null,
        ?TransformationExecution $execution = null,
        ?Iterator $iterator = null,
    ) : self {
        return new self(
            input: $input ?? $this->input,
            transformation: $transformation ?? $this->transformation,
            execution: $execution ?? $this->execution,
            iterator: $iterator ?? $this->iterator,
        );
    }

    private function iterate(TransformationExecution $transduction, SplQueue $queue): Iterator {
        while ($transduction->hasNextStep()) {
            // Phase 1) Perform a step in the transduction process - this may enqueue multiple items
            $transduction->step();
            while (!$queue->isEmpty()) {
                // Phase 2) Yield all items currently in the queue until it's empty
                yield $queue->dequeue();
            }
        }
        // Completion phase: allow reducers to flush final values (e.g., finalize sequences)
        $transduction->completed();
        while (!$queue->isEmpty()) {
            yield $queue->dequeue();
        }
    }

    private function execution(): TransformationExecution {
        if ($this->execution === null) {
            $this->execution = $this->makeExecution();
        }
        return $this->execution;
    }

    private function makeExecution() : TransformationExecution {
        $this->queue = new SplQueue();
        $sink = new ToQueueReducer($this->queue);
        $transformation = $this->transformation->withSink($sink);
        if ($this->input !== []) {
            $transformation = $transformation->withInput($this->input);
        }
        return $transformation->execution();
    }

    private function makeIterator(TransformationExecution $execution) : Iterator {
        if ($this->execution === null || $this->queue === null) {
            throw new \LogicException('Execution not initialized');
        }
        $this->iterator = $this->iterate($this->execution, $this->queue);
        return $this->iterator;
    }
}

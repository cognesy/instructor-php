<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials\ToolCallMode;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handle tool call signals and state management.
 * Preprocesses PartialInferenceResponse to handle tool call lifecycle.
 *
 * Input:  PartialInferenceResponse
 * Output: PartialInferenceResponse (potentially modified)
 * State:  ToolCallStreamState (tracks active tool call)
 */
final readonly class HandleToolCallSignals implements Transducer
{
    public function __construct(
        private string $expectedToolName,
        private EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new HandleToolCallSignalsReducer(
            inner: $reducer,
            expectedToolName: $this->expectedToolName,
            events: $this->events,
        );
    }
}

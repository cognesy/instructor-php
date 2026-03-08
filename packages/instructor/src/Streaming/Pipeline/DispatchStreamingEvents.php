<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Pipeline;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Transducer that dispatches streaming events as partial responses flow through the pipeline.
 */
final readonly class DispatchStreamingEvents implements Transducer
{
    public function __construct(
        private CanHandleEvents $events,
        private string $expectedToolName = '',
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DispatchStreamingEventsReducer(
            inner: $reducer,
            events: $this->events,
            expectedToolName: $this->expectedToolName,
        );
    }
}

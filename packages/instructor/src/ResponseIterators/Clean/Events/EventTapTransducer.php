<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Events;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Event tap transducer.
 *
 * Creates EventTap reducer for event dispatch.
 */
final readonly class EventTapTransducer implements Transducer
{
    public function __construct(
        private CanHandleEvents $events,
        private string $expectedToolName = '',
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new EventTap(
            inner: $reducer,
            events: $this->events,
            expectedToolName: $this->expectedToolName,
        );
    }
}

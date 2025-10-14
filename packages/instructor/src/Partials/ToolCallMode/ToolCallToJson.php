<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\ToolCallMode;

use Cognesy\Instructor\Partials\ToolCallMode\ToolCallToJsonReducer;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Convert tool call state updates to JSON context.
 * Bridges tool call handling with JSON deserialization pipeline.
 *
 * Input:  PartialContext (with toolCallUpdate)
 * Output: PartialContext (with json)
 * State:  Stateless
 */
final readonly class ToolCallToJson implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ToolCallToJsonReducer(inner: $reducer);
    }
}

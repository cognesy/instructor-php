<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials\JsonMode;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Assemble JSON from deltas using PartialJson value object.
 *
 * Input:  PartialContext (with delta)
 * Output: PartialContext (with json)
 * State:  PartialJson (accumulated)
 */
final readonly class AssembleJson implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new AssembleJsonReducer(inner: $reducer);
    }
}

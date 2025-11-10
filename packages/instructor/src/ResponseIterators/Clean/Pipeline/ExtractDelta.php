<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Pipeline;

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Extract delta from PartialInferenceResponse based on output mode.
 *
 * Trans Transducer that creates ExtractDeltaReducer.
 */
final readonly class ExtractDelta implements Transducer
{
    public function __construct(
        private OutputMode $mode,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ExtractDeltaReducer(
            inner: $reducer,
            mode: $this->mode,
        );
    }
}

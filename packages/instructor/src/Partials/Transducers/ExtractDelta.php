<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Transducers;

use Cognesy\Instructor\Partials\Reducers\ExtractDeltaReducer;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Extract delta from PartialInferenceResponse based on output mode.
 *
 * Input:  PartialInferenceResponse
 * Output: PartialContext
 * State:  Stateless
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

<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Pipeline;

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Enrich PartialFrame back to PartialInferenceResponse for emission.
 *
 * Transducer that creates EnrichResponseReducer.
 */
final readonly class EnrichResponse implements Transducer
{
    public function __construct(
        private OutputMode $mode,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new EnrichResponseReducer(
            inner: $reducer,
            mode: $this->mode,
        );
    }
}

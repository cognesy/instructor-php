<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Closure;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
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
    /**
     * @param Closure(OutputMode): CanBufferContent|null $bufferFactory Optional factory for content buffer
     */
    public function __construct(
        private OutputMode $mode,
        private ?Closure $bufferFactory = null,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ExtractDeltaReducer(
            inner: $reducer,
            mode: $this->mode,
            bufferFactory: $this->bufferFactory,
        );
    }
}

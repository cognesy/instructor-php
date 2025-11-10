<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\DeltaExtraction;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;

class ExtractDeltaReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        private OutputMode $mode,
    ) {}

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        $delta = match ($this->mode) {
            OutputMode::Tools => $reducible->toolArgs ?: $reducible->contentDelta,
            default => $reducible->contentDelta,
        };

        if ($this->shouldSkip($reducible, $delta)) {
            return $accumulator;
        }

        return $this->inner->step(
            accumulator: $accumulator,
            reducible: PartialProcessingState::fromResponse($reducible)->withDelta($delta),
        );
    }

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function shouldSkip(PartialInferenceResponse $reducible, string $delta) : bool {
        return $delta === ''
            && $reducible->finishReason === ''
            && !$reducible->hasValue();
    }
}

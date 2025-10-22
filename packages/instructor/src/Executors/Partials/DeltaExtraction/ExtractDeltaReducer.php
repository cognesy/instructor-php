<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials\DeltaExtraction;

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

        // Forward when there is something meaningful to observe:
        // - non-empty delta
        // - OR finish signal
        // - OR driver-provided value (pre-deserialized)
        if ($delta === '' && $reducible->finishReason === '' && !$reducible->hasValue()) {
            return $accumulator;
        }

        $context = PartialProcessingState::fromResponse($reducible)->withDelta($delta);
        return $this->inner->step($accumulator, $context);
    }

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}

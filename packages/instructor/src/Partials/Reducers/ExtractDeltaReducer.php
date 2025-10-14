<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Reducers;

use Cognesy\Instructor\Partials\Data\PartialContext;
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

        // If delta is empty but finishReason is present, still forward
        if ($delta === '' && $reducible->finishReason === '') {
            return $accumulator;
        }

        $context = PartialContext::fromResponse($reducible)->withDelta($delta);
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

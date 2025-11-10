<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Pipeline;

use Cognesy\Instructor\ResponseIterators\Clean\Domain\PartialFrame;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Enriches PartialFrame back to PartialInferenceResponse for emission.
 *
 * Only forwards frames marked for emission (explicit Emission enum).
 * Converts PartialFrame → PartialInferenceResponse.
 *
 * For Tools mode: uses buffer content (accumulated toolArgs)
 * For other modes: uses source content (cumulative from driver)
 */
final class EnrichResponseReducer implements Reducer
{
    public function __construct(
        private readonly Reducer $inner,
        private readonly OutputMode $mode,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialFrame);

        // For Tools mode: use buffer content (accumulated toolArgs across chunks)
        // For other modes: use source content (cumulative from driver)
        $forward = match ($this->mode) {
            OutputMode::Tools => $reducible->toPartialResponse(),
            default => $this->forwardWithValue($reducible),
        };

        return $this->inner->step($accumulator, $forward);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    private function forwardWithValue(PartialFrame $frame): PartialInferenceResponse {
        // Use source's cumulative content, but attach the deserialized value
        $forward = $frame->source;
        if ($frame->hasObject()) {
            $forward = $forward->withValue($frame->object->unwrap());
        }
        return $forward;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Enriches PartialFrame back to PartialInferenceResponse.
 *
 * Forwards all frames downstream after enrichment.
 * EmissionType controls event dispatch in EventTap, not forwarding here.
 *
 * For Tools mode: uses buffer content built from toolCalls() snapshot.
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

        // For Tools mode: use normalized content from current toolCalls() snapshot
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

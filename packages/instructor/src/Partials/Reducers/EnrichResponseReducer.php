<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Reducers;

use Cognesy\Instructor\Partials\Data\PartialContext;
use Cognesy\Stream\Contracts\Reducer;

class EnrichResponseReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
    ) {}

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialContext);

        // Always forward a PartialInferenceResponse so downstream aggregate can
        // track usage/partial counts. On non-emitting steps, zero inputTokens
        // to avoid accumulating request-level input across chunks.
        if (!$reducible->shouldEmit) {
            $u = $reducible->response->usage();
            $forwardUsage = new \Cognesy\Polyglot\Inference\Data\Usage(
                inputTokens: 0,
                outputTokens: $u->outputTokens,
                cacheWriteTokens: $u->cacheWriteTokens,
                cacheReadTokens: $u->cacheReadTokens,
                reasoningTokens: $u->reasoningTokens,
            );
            $forward = $reducible->response->with(usage: $forwardUsage);
            return $this->inner->step($accumulator, $forward);
        }

        // Convert back to PartialInferenceResponse when emittable
        $enriched = $reducible->toPartialResponse();
        return $this->inner->step($accumulator, $enriched);
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

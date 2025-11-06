<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Iterator;
use IteratorAggregate;

/**
 * Carries ephemeral, in-process attempt state for a single structured-output attempt (sync and streamed)
 *
 * Notes:
 * - This object is intentionally not serialized; it may contain a live Iterator.
 * - It is format-agnostic: works with any extraction mode (JSON content, tool calls, etc.).
 * - All mutating operations return a new instance (functional style) to keep outer state immutable.
 */
final readonly class StructuredOutputAttemptState
{
    /**
     * @param AttemptPhase $attemptPhase Phase of attempt processing
     * @param Iterator|null $stream Live iterator over streamed chunks (always unwrapped, never IteratorAggregate)
     * @param int $partialIndex Count of processed chunks for the current attempt
     * @param bool $streamExhausted Whether the stream has been fully consumed
     * @param InferenceResponse|null $lastInference Last aggregated inference response observed
     * @param PartialInferenceResponseList $accumulatedPartials Accumulated partial responses so far
     */
    public function __construct(
        private AttemptPhase $attemptPhase,
        private ?Iterator $stream,
        private int $partialIndex,
        private bool $streamExhausted,
        private ?InferenceResponse $lastInference,
        private PartialInferenceResponseList $accumulatedPartials,
    ) {}

    // ACCESSORS /////////////////////////////////////////////////////////

    public static function empty(): self {
        return new self(
            attemptPhase: AttemptPhase::Init,
            stream: null,
            partialIndex: 0,
            streamExhausted: false,
            lastInference: null,
            accumulatedPartials: PartialInferenceResponseList::empty(),
        );
    }

    /**
     * Creates a cleared streaming state (marks stream as exhausted/done).
     * Used after finalizing an attempt to ensure no further streaming occurs.
     */
    public static function cleared(): self {
        return new self(
            attemptPhase: AttemptPhase::Done,
            stream: null,
            partialIndex: 0,
            streamExhausted: true,
            lastInference: null,
            accumulatedPartials: PartialInferenceResponseList::empty(),
        );
    }

    /**
     * Factory for a single-chunk (sync) attempt state:
     * - Sets phase to provided value (default Done)
     * - Records the single inference and partials
     * - Marks as exhausted to indicate no more chunks in this attempt
     */
    public static function fromSingleChunk(
        InferenceResponse $inference,
        PartialInferenceResponseList $partials,
        AttemptPhase $phase = AttemptPhase::Done,
    ): self {
        return self::empty()
            ->withPhase($phase)
            ->withNextChunk($inference, $partials, true);
    }

    public function attemptPhase(): AttemptPhase {
        return $this->attemptPhase;
    }

    public function stream(): ?Iterator {
        return $this->stream;
    }

    public function partialIndex(): int {
        return $this->partialIndex;
    }

    public function lastInference(): ?InferenceResponse {
        return $this->lastInference;
    }

    public function accumulatedPartials(): PartialInferenceResponseList {
        return $this->accumulatedPartials;
    }

    /**
     * Check if stream has more chunks to process.
     *
     * IMPORTANT: We rely on the streamExhausted flag instead of calling valid()
     * on the iterator because:
     * 1. The iterator might be shared across multiple execution instances (immutability issue)
     * 2. Generators cannot be checked twice - valid() on exhausted generator may fail
     * 3. The exhausted flag is set by the processor after calling next(), so it's authoritative
     */
    public function hasMoreChunks(): bool {
        // Check exhausted flag - this is the ONLY reliable way to know if stream is done
        // DO NOT call valid() on the iterator as it may be shared/exhausted
        if ($this->streamExhausted) {
            return false;
        }

        // Stream not yet started or not yet exhausted
        return $this->stream !== null;
    }

    /**
     * Check if stream is initialized (not null).
     */
    public function isStreamInitialized(): bool {
        return $this->stream !== null;
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function withPhase(AttemptPhase $attemptPhase): self {
        return $this->with(attemptPhase: $attemptPhase);
    }

    public function withStream(Iterator|IteratorAggregate $stream): self {
        // IMPORTANT: Unwrap IteratorAggregate immediately to avoid calling getIterator() multiple times
        // (generators cannot be rewound!)
        $iterator = $stream instanceof IteratorAggregate ? $stream->getIterator() : $stream;
        assert($iterator instanceof Iterator);
        return $this->with(stream: $iterator);
    }

    /**
     * Create a new state after processing next chunk.
     * Automatically increments partialIndex.
     *
     * IMPORTANT: Caller must pass $isExhausted after checking iterator->valid()
     * on the SAME iterator instance (generators cannot be rewound!).
     */
    public function withNextChunk(
        InferenceResponse $inference,
        PartialInferenceResponseList $partials,
        bool $isExhausted,
    ): self {
        return $this->with(
            partialIndex: $this->partialIndex + 1,
            streamExhausted: $isExhausted,
            lastInference: $inference,
            accumulatedPartials: $partials,
        );
    }

    /**
     * Mark stream as exhausted (no more chunks available).
     */
    public function withExhausted(): self {
        return $this->with(streamExhausted: true);
    }

    // INTERNAL //////////////////////////////////////////////////////////

    private function with(
        ?AttemptPhase $attemptPhase = null,
        ?Iterator $stream = null,
        ?int $partialIndex = null,
        ?bool $streamExhausted = null,
        ?InferenceResponse $lastInference = null,
        ?PartialInferenceResponseList $accumulatedPartials = null,
    ): self {
        return new self(
            attemptPhase: $attemptPhase ?? $this->attemptPhase,
            stream: $stream ?? $this->stream,
            partialIndex: $partialIndex ?? $this->partialIndex,
            streamExhausted: $streamExhausted ?? $this->streamExhausted,
            lastInference: $lastInference ?? $this->lastInference,
            accumulatedPartials: $accumulatedPartials ?? $this->accumulatedPartials,
        );
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

/**
 * Structured-output view over the shared stream accumulator.
 *
 * Delta/tool/usage accumulation is delegated to polyglot's
 * InferenceStreamState (single owner of tool-key semantics — including
 * repeated-name continuation and pre-key args buffering). This class adds
 * what structured output needs on top: the materialized value, snapshot
 * revisions for the throttle, memoized derived objects, and
 * StructuredOutputResponse construction.
 */
final class StructuredOutputStreamState
{
    private InferenceStreamState $inner;

    private int $snapshotRevision = 0;
    private mixed $value = null;
    private ?ToolCalls $memoizedToolCalls = null;
    private ?EmissionSnapshot $memoizedSnapshot = null;

    public function __construct()
    {
        $this->inner = new InferenceStreamState();
    }

    public static function empty(): self
    {
        return new self();
    }

    public function reset(): void
    {
        $this->inner = new InferenceStreamState();
        $this->snapshotRevision = 0;
        $this->value = null;
        $this->memoizedToolCalls = null;
        $this->memoizedSnapshot = null;
    }

    public function applyDelta(PartialInferenceDelta $delta): void
    {
        $this->invalidateDerivedState();
        $this->inner->applyDelta($delta);

        if ($delta->contentDelta !== '' || $delta->toolArgs !== '') {
            $this->snapshotRevision += 1;
        }
    }

    public function setValue(mixed $value): void
    {
        $this->memoizedSnapshot = null;
        $this->value = $value;
    }

    public function clearValue(): void
    {
        $this->memoizedSnapshot = null;
        $this->value = null;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public function content(): string
    {
        return $this->inner->content();
    }

    public function reasoningContent(): string
    {
        return $this->inner->reasoningContent();
    }

    public function finishReason(): string
    {
        return $this->inner->finishReason();
    }

    public function snapshotRevision(): int
    {
        return $this->snapshotRevision;
    }

    public function usage(): InferenceUsage
    {
        return $this->inner->usage();
    }

    public function toolArgsSnapshot(): string
    {
        return $this->inner->toolArgsSnapshot();
    }

    public function toolKey(): string
    {
        return $this->inner->toolKey();
    }

    public function toolCalls(): ToolCalls
    {
        return $this->memoizedToolCalls ??= $this->inner->toolCalls();
    }

    public function snapshot(): EmissionSnapshot
    {
        if ($this->memoizedSnapshot instanceof EmissionSnapshot) {
            return $this->memoizedSnapshot;
        }

        return $this->memoizedSnapshot = new EmissionSnapshot(
            content: $this->content(),
            finishReason: $this->finishReason(),
            toolKey: $this->toolKey(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
            value: $this->value,
        );
    }

    public function partialInferenceResponse(): InferenceResponse
    {
        return (new InferenceResponse(
            content: $this->content(),
            finishReason: $this->finishReason(),
            toolCalls: $this->toolCalls(),
            reasoningContent: $this->reasoningContent(),
            usage: $this->usage(),
            isPartial: true,
        ))->withReasoningContentFallbackFromContent();
    }

    public function partialResponse(): StructuredOutputResponse
    {
        return StructuredOutputResponse::partial(
            value: $this->value,
            inferenceResponse: $this->partialInferenceResponse(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
        );
    }

    public function finalInferenceResponse(): InferenceResponse
    {
        return (new InferenceResponse(
            content: $this->content(),
            finishReason: $this->finishReason(),
            toolCalls: $this->toolCalls(),
            reasoningContent: $this->reasoningContent(),
            usage: $this->usage(),
            isPartial: false,
        ))->withReasoningContentFallbackFromContent();
    }

    public function finalResponse(): StructuredOutputResponse
    {
        return StructuredOutputResponse::final(
            value: $this->value,
            inferenceResponse: $this->finalInferenceResponse(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
        );
    }

    private function invalidateDerivedState(): void
    {
        $this->memoizedToolCalls = null;
        $this->memoizedSnapshot = null;
    }
}

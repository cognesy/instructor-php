<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Assembly\ThinkTagMessageNormalizer;
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
    private bool $hasPrebuiltValue = false;
    private ?ToolCalls $memoizedToolCalls = null;
    private ?EmissionSnapshot $memoizedSnapshot = null;
    private ?InferenceResponse $memoizedPartialInferenceResponse = null;
    private ?InferenceResponse $memoizedFinalInferenceResponse = null;
    private ?StructuredOutputResponse $memoizedPartialResponse = null;
    private ?StructuredOutputResponse $memoizedFinalResponse = null;

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
        $this->hasPrebuiltValue = false;
        $this->memoizedToolCalls = null;
        $this->memoizedSnapshot = null;
        $this->memoizedPartialInferenceResponse = null;
        $this->memoizedFinalInferenceResponse = null;
        $this->memoizedPartialResponse = null;
        $this->memoizedFinalResponse = null;
    }

    public function applyDelta(PartialInferenceDelta $delta): void
    {
        $this->invalidateDerivedState();
        $this->inner->applyDelta($delta);

        if (!$delta->messageChunks->isEmpty()) {
            $this->snapshotRevision += 1;
        }
    }

    public function setValue(mixed $value): void
    {
        if ($this->hasPrebuiltValue && self::sameValue($this->value, $value)) {
            return;
        }
        $this->invalidateDerivedState();
        $this->value = $value;
        $this->hasPrebuiltValue = true;
    }

    public function setPreview(mixed $value): void
    {
        if (!$this->hasPrebuiltValue && self::sameValue($this->value, $value)) {
            return;
        }
        $this->invalidateDerivedState();
        $this->value = $value;
        $this->hasPrebuiltValue = false;
    }

    public function clearValue(): void
    {
        if ($this->value === null && !$this->hasPrebuiltValue) {
            return;
        }
        $this->invalidateDerivedState();
        $this->value = null;
        $this->hasPrebuiltValue = false;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public function materializationInput(): mixed
    {
        return match ($this->hasPrebuiltValue) {
            true => $this->value,
            false => null,
        };
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

    public function currentToolCall(): ?ToolCall
    {
        return $this->inner->currentToolCall();
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
        if ($this->memoizedPartialInferenceResponse instanceof InferenceResponse) {
            return $this->memoizedPartialInferenceResponse;
        }
        $response = $this->inner->partialResponse();
        return $this->memoizedPartialInferenceResponse = self::normalize($response);
    }

    public function partialResponse(): StructuredOutputResponse
    {
        return $this->memoizedPartialResponse ??= StructuredOutputResponse::partial(
            value: $this->value,
            inferenceResponse: $this->partialInferenceResponse(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
        );
    }

    public function finalInferenceResponse(): InferenceResponse
    {
        if ($this->memoizedFinalInferenceResponse instanceof InferenceResponse) {
            return $this->memoizedFinalInferenceResponse;
        }
        $response = $this->inner->finalResponse();
        return $this->memoizedFinalInferenceResponse = self::normalize($response);
    }

    public function finalResponse(): StructuredOutputResponse
    {
        return $this->memoizedFinalResponse ??= StructuredOutputResponse::final(
            value: $this->value,
            inferenceResponse: $this->finalInferenceResponse(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
        );
    }

    private function invalidateDerivedState(): void
    {
        $this->memoizedToolCalls = null;
        $this->memoizedSnapshot = null;
        $this->memoizedPartialInferenceResponse = null;
        $this->memoizedFinalInferenceResponse = null;
        $this->memoizedPartialResponse = null;
        $this->memoizedFinalResponse = null;
    }

    private static function normalize(InferenceResponse $response): InferenceResponse
    {
        $message = ThinkTagMessageNormalizer::normalize($response->message());
        return match ($message === $response->message()) {
            true => $response,
            false => $response->withMessage($message),
        };
    }

    private static function sameValue(mixed $current, mixed $next): bool
    {
        return match (true) {
            is_object($current) || is_object($next) => $current === $next,
            default => $current === $next,
        };
    }
}

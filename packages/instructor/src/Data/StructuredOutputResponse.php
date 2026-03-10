<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final readonly class StructuredOutputResponse
{
    public static function partial(
        mixed $value,
        InferenceResponse $inferenceResponse,
        string $toolArgsSnapshot = '',
    ): self {
        return new self(
            value: $value,
            inferenceResponse: $inferenceResponse,
            isPartial: true,
            toolArgsSnapshot: $toolArgsSnapshot,
        );
    }

    public static function final(
        mixed $value,
        InferenceResponse $inferenceResponse,
        string $toolArgsSnapshot = '',
    ): self {
        return new self(
            value: $value,
            inferenceResponse: $inferenceResponse,
            isPartial: false,
            toolArgsSnapshot: $toolArgsSnapshot,
        );
    }

    public function __construct(
        private mixed $value,
        private InferenceResponse $inferenceResponse,
        private bool $isPartial = false,
        private string $toolArgsSnapshot = '',
    ) {}

    public function value(): mixed
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public function inferenceResponse(): InferenceResponse
    {
        return $this->inferenceResponse;
    }

    public function isPartial(): bool
    {
        return $this->isPartial;
    }

    public function isFinal(): bool
    {
        return !$this->isPartial;
    }

    public function content(): string
    {
        return $this->inferenceResponse->content();
    }

    public function reasoningContent(): string
    {
        return $this->inferenceResponse->reasoningContent();
    }

    public function toolCalls(): ToolCalls
    {
        return $this->inferenceResponse->toolCalls();
    }

    public function toolArgsSnapshot(): string
    {
        return $this->toolArgsSnapshot;
    }

    public function usage(): Usage
    {
        return $this->inferenceResponse->usage();
    }

    public function finishReason(): InferenceFinishReason
    {
        return $this->inferenceResponse->finishReason();
    }

    public function responseData(): HttpResponse
    {
        return $this->inferenceResponse->responseData();
    }
}

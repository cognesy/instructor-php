<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final readonly class StructuredOutputResponse
{
    public static function partial(
        mixed $value,
        InferenceResponse $rawResponse,
        string $toolArgsSnapshot = '',
    ): self {
        return new self(
            value: $value,
            rawResponse: $rawResponse,
            isPartial: true,
            toolArgsSnapshot: $toolArgsSnapshot,
        );
    }

    public static function final(
        mixed $value,
        InferenceResponse $rawResponse,
        string $toolArgsSnapshot = '',
    ): self {
        return new self(
            value: $value,
            rawResponse: $rawResponse,
            isPartial: false,
            toolArgsSnapshot: $toolArgsSnapshot,
        );
    }

    public function __construct(
        private mixed $value,
        private InferenceResponse $rawResponse,
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

    public function rawResponse(): InferenceResponse
    {
        return $this->rawResponse;
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
        return $this->rawResponse->content();
    }

    public function reasoningContent(): string
    {
        return $this->rawResponse->reasoningContent();
    }

    public function toolCalls(): ToolCalls
    {
        return $this->rawResponse->toolCalls();
    }

    public function toolArgsSnapshot(): string
    {
        return $this->toolArgsSnapshot;
    }

    public function usage(): Usage
    {
        return $this->rawResponse->usage();
    }

    public function finishReason(): InferenceFinishReason
    {
        return $this->rawResponse->finishReason();
    }

    public function responseData(): HttpResponse
    {
        return $this->rawResponse->responseData();
    }
}

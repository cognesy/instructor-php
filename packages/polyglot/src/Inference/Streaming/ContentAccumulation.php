<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

class ContentAccumulation
{
    public function __construct(
        public readonly string $content,
        public readonly string $reasoningContent,
        public readonly string $finishReason,
    ) {}

    public static function empty(): self {
        return new self('', '', '');
    }

    public function withPartialResponse(PartialInferenceResponse $partialResponse): self {
        return new self(
            content: $this->content . $partialResponse->contentDelta,
            reasoningContent: $this->reasoningContent . $partialResponse->reasoningContentDelta,
            finishReason: $partialResponse->finishReason !== '' ? $partialResponse->finishReason : $this->finishReason,
        );
    }
}

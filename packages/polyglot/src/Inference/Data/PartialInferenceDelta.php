<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Http\Data\HttpResponse;

/**
 * Typed delta payload parsed from one streaming event.
 */
final readonly class PartialInferenceDelta
{
    public function __construct(
        public AssistantMessageChunks $messageChunks = new AssistantMessageChunks(),
        public string $finishReason = '',
        public ?InferenceUsage $usage = null,
        public bool $usageIsCumulative = false,
        public ?HttpResponse $responseData = null,
        public mixed $value = null,
        public ?ReplayEnvelope $replay = null,
    ) {}

    public function withMessageChunks(AssistantMessageChunks $messageChunks): self
    {
        return new self(
            messageChunks: $messageChunks,
            finishReason: $this->finishReason,
            usage: $this->usage,
            usageIsCumulative: $this->usageIsCumulative,
            responseData: $this->responseData,
            value: $this->value,
            replay: $this->replay,
        );
    }
}

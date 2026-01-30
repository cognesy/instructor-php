<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

final readonly class HookContext
{
    public function __construct(
        public ?ToolCall $toolCall = null,
        public ?ToolExecution $toolExecution = null,
        public ?Messages $inferenceMessages = null,
        public ?InferenceResponse $inferenceResponse = null,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public function withToolCall(?ToolCall $toolCall): self
    {
        return new self(
            toolCall: $toolCall,
            toolExecution: $this->toolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $this->inferenceResponse,
        );
    }

    public function withToolExecution(?ToolExecution $toolExecution): self
    {
        return new self(
            toolCall: $this->toolCall,
            toolExecution: $toolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $this->inferenceResponse,
        );
    }

    public function withInferenceMessages(?Messages $messages): self
    {
        return new self(
            toolCall: $this->toolCall,
            toolExecution: $this->toolExecution,
            inferenceMessages: $messages,
            inferenceResponse: $this->inferenceResponse,
        );
    }

    public function withInferenceResponse(?InferenceResponse $response): self
    {
        return new self(
            toolCall: $this->toolCall,
            toolExecution: $this->toolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $response,
        );
    }
}

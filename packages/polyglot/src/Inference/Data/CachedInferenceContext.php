<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\Messages;

class CachedInferenceContext
{
    private Messages $messages;

    private ToolDefinitions $tools;

    private ToolChoice $toolChoice;

    private ResponseFormat $responseFormat;

    public function __construct(
        Messages|string|array $messages = [],
        ToolDefinitions|array $tools = [],
        ToolChoice|string|array $toolChoice = [],
        array|ResponseFormat|null $responseFormat = null,
    ) {
        $this->messages = Messages::fromAny($messages);
        $this->tools = match (true) {
            $tools instanceof ToolDefinitions => $tools,
            default => ToolDefinitions::fromArray($tools),
        };
        $this->toolChoice = ToolChoice::fromAny($toolChoice);
        $this->responseFormat = match (true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
            default => new ResponseFormat,
        };
    }

    public function messages(): Messages
    {
        return $this->messages;
    }

    public function tools(): ToolDefinitions
    {
        return $this->tools;
    }

    public function toolChoice(): ToolChoice
    {
        return $this->toolChoice;
    }

    public function responseFormat(): ResponseFormat
    {
        return $this->responseFormat;
    }

    public function withMessages(Messages|string|array $messages): self
    {
        return new self(
            messages: $messages,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withTools(ToolDefinitions|array $tools): self
    {
        return new self(
            messages: $this->messages,
            tools: $tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withToolChoice(ToolChoice|string|array $toolChoice): self
    {
        return new self(
            messages: $this->messages,
            tools: $this->tools,
            toolChoice: $toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withResponseFormat(array|ResponseFormat $responseFormat): self
    {
        return new self(
            messages: $this->messages,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $responseFormat,
        );
    }

    public function isEmpty(): bool
    {
        return $this->messages->isEmpty()
            && $this->tools->isEmpty()
            && $this->toolChoice->isEmpty()
            && $this->responseFormat->isEmpty();
    }

    public function toArray(): array
    {
        return [
            'messages' => $this->messages->toArray(),
            'tools' => $this->tools->toArray(),
            'toolChoice' => $this->toolChoice->toArray(),
            'responseFormat' => $this->responseFormat->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            messages: $data['messages'] ?? [],
            tools: $data['tools'] ?? [],
            toolChoice: $data['toolChoice'] ?? [],
            responseFormat: is_array($data['responseFormat'] ?? null) ? $data['responseFormat'] : [],
        );
    }
}

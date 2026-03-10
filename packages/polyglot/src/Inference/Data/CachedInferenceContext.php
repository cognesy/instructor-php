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
        ?Messages $messages = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
    ) {
        $this->messages = $messages ?? Messages::empty();
        $this->tools = $tools ?? ToolDefinitions::empty();
        $this->toolChoice = $toolChoice ?? ToolChoice::empty();
        $this->responseFormat = $responseFormat ?? ResponseFormat::empty();
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

    public function withMessages(Messages $messages): self
    {
        return new self(
            messages: $messages,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withTools(ToolDefinitions $tools): self
    {
        return new self(
            messages: $this->messages,
            tools: $tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withToolChoice(ToolChoice $toolChoice): self
    {
        return new self(
            messages: $this->messages,
            tools: $this->tools,
            toolChoice: $toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withResponseFormat(ResponseFormat $responseFormat): self
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
            messages: self::messagesFromArray($data),
            tools: self::toolsFromArray($data),
            toolChoice: self::toolChoiceFromArray($data),
            responseFormat: self::responseFormatFromArray($data),
        );
    }

    private static function messagesFromArray(array $data): Messages
    {
        $messages = $data['messages'] ?? [];

        return match (true) {
            $messages instanceof Messages => $messages,
            is_array($messages) => Messages::fromAnyArray($messages),
            is_string($messages) => Messages::fromString($messages),
            default => Messages::empty(),
        };
    }

    private static function toolsFromArray(array $data): ToolDefinitions
    {
        $tools = $data['tools'] ?? [];

        return match (true) {
            $tools instanceof ToolDefinitions => $tools,
            is_array($tools) => ToolDefinitions::fromArray($tools),
            default => ToolDefinitions::empty(),
        };
    }

    private static function toolChoiceFromArray(array $data): ToolChoice
    {
        $toolChoice = $data['toolChoice'] ?? [];

        return match (true) {
            $toolChoice instanceof ToolChoice => $toolChoice,
            is_string($toolChoice), is_array($toolChoice) => ToolChoice::fromAny($toolChoice),
            default => ToolChoice::empty(),
        };
    }

    private static function responseFormatFromArray(array $data): ResponseFormat
    {
        $responseFormat = $data['responseFormat'] ?? null;

        return match (true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
            default => ResponseFormat::empty(),
        };
    }
}

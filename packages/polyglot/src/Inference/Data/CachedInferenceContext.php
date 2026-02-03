<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\Messages;

class CachedInferenceContext
{
    private Messages $messages;
    private array $tools;
    private string|array $toolChoice;
    private ResponseFormat $responseFormat;

    public function __construct(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array|ResponseFormat|null $responseFormat = null,
    ) {
        $this->messages = Messages::fromAny($messages);
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = match(true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
            default => new ResponseFormat(),
        };
    }

    public function messages() : Messages {
        return $this->messages;
    }

    public function tools() : array {
        return $this->tools;
    }

    public function toolChoice() : string|array {
        return $this->toolChoice;
    }

    public function responseFormat() : ResponseFormat {
        return $this->responseFormat;
    }

    public function withMessages(Messages|string|array $messages): self {
        return new self(
            messages: $messages instanceof Messages ? $messages->toArray() : $messages,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withTools(array $tools): self {
        return new self(
            messages: $this->messages->toArray(),
            tools: $tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }

    public function withResponseFormat(array|ResponseFormat $responseFormat): self {
        return new self(
            messages: $this->messages->toArray(),
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $responseFormat,
        );
    }

    public function isEmpty() : bool {
        return $this->messages->isEmpty()
            && empty($this->tools)
            && empty($this->toolChoice)
            && $this->responseFormat->isEmpty();
    }

    public function toArray(): array {
        return [
            'messages' => $this->messages->toArray(),
            'tools' => $this->tools,
            'toolChoice' => $this->toolChoice,
            'responseFormat' => $this->responseFormat->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            messages: $data['messages'] ?? [],
            tools: $data['tools'] ?? [],
            toolChoice: $data['toolChoice'] ?? [],
            responseFormat: is_array($data['responseFormat'] ?? null) ? $data['responseFormat'] : [],
        );
    }
}

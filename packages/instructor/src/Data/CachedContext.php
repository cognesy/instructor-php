<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Utils\Messages\Messages;

class CachedContext
{
    private Messages $messages;
    private string $system;
    private string $prompt;
    private array $examples;

    public function __construct(
        string|array $messages = [],
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) {
        $this->messages = match(true) {
            is_string($messages) => Messages::fromString($messages),
            is_array($messages) => Messages::fromArray($messages),
        };
        $this->system = $system;
        $this->prompt = $prompt;
        $this->examples = $examples;
    }

    public function messages() : Messages {
        return $this->messages;
    }

    public function system() : string {
        return $this->system;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function toArray() : array {
        return [
            'messages' => $this->messages,
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples,
        ];
    }

    public function isEmpty() : bool {
        return empty($this->messages)
            && empty($this->system)
            && empty($this->prompt)
            && empty($this->examples);
    }

    public static function fromArray(array $data) : static {
        if (empty($data)) {
            return new CachedContext();
        }

        return new CachedContext(
            messages: $data['messages'] ?? '',
            system: $data['system'] ?? '',
            prompt: $data['prompt'] ?? '',
            examples: $data['examples'] ?? [],
        );
    }
}
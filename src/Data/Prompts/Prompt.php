<?php

namespace Cognesy\Instructor\Data\Prompts;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Utils\Template;

class Prompt
{
    private array $variants;
    private string $selected = '*';

    public function __construct(string|array $variants = []) {
        $this->variants = match(true) {
            is_string($variants) => ['*' => $variants],
            default => $variants,
        };
        if (is_array($this->variants) && !empty($variants)) {
            $this->selected = array_key_first($this->variants);
        }
    }

    public function select(string $variant = '*') : static {
        $this->selected = $variant;
        return $this;
    }

    public function variants() : array {
        return array_keys($this->variants);
    }

    public function toArray(string $role = 'user') : array {
        return [
            'role' => $role,
            'content' => $this->variants[$this->selected],
        ];
    }

    public function toMessage(string $role = 'user') : Message {
        return Message::fromArray(
            $this->toArray($role)
        );
    }

    public function render(array $context = []) : string {
        return match(true) {
            empty($context) => $this->variants[$this->selected],
            default => (new Template($context))->renderString($this->variants[$this->selected]),
        };
    }
}
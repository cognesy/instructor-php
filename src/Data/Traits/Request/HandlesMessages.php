<?php
namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesMessages
{
    private string|array $messages;
    private string|array|object $input = '';

    public function input(): string|array|object {
        return $this->input;
    }

    public function messages() : array {
        if (is_string($this->messages)) {
            return [['role' => 'user', 'content' => $this->messages]];
        }
        return $this->messages;
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function withMessages(array $messages) : self {
        $this->messages = $messages;
        return $this;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }
}
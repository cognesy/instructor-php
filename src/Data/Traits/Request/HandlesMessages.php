<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Core\Messages\Script;

trait HandlesMessages
{
    private string|array $messages;
    private Script $script;

    public function messages() : array {
        if (is_string($this->messages)) {
            return [['role' => 'user', 'content' => $this->messages]];
        }
        return $this->messages;
    }

    public function withMessages(array $messages) : self {
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
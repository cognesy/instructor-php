<?php

namespace Cognesy\Instructor\Data\Traits;

trait HandlesMessages
{
    private string|array $messages;

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
}
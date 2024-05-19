<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

trait HandlesMessages
{
    public function messages(): array {
        return $this->messages;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }
}
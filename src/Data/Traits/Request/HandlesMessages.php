<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Data\Messages\Script;

trait HandlesMessages
{
    private Script $script;
    private string|array $messages;

    public function script() : Script {
        return $this->script;
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
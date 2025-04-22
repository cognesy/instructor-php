<?php

namespace Cognesy\Utils\Messages\Traits\Message;

trait HandlesMutation
{
    public function addContentPart(string|array $part, string $role = 'user') : static {
        if (!empty($this->role)) {
            $this->role = $role;
        }

        $this->content = array_merge($this->content, match(true) {
            is_string($part) => [
                'type' => 'text',
                'content' => $part,
            ],
            default => $part,
        });

        return $this;
    }

    public function withRole(string $role) : static {
        $this->role = $role;
        return $this;
    }
}

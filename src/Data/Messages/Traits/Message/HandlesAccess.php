<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Message;

use Cognesy\Instructor\Data\Messages\Enums\MessageRole;

trait HandlesAccess
{
    public function role() : MessageRole {
        return MessageRole::fromString($this->role);
    }

    public function content() : string|array {
        return $this->content;
    }

    public function isEmpty() : bool {
        return empty($this->content);
    }

    public function isNull() : bool {
        return ($this->role === '' && $this->content === '');
    }

    public function isComposite() : bool {
        return is_array($this->content);
    }

    protected static function hasRoleAndContent(array $message) : bool {
        return isset($message['role'], $message['content']);
    }
}
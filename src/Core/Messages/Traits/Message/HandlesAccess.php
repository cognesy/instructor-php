<?php

namespace Cognesy\Instructor\Core\Messages\Traits\Message;

use Cognesy\Instructor\Core\Messages\Enums\MessageRole;
use Cognesy\Instructor\Core\Messages\Message;

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
}
<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\Data\Messages\Enums\MessageRole;

trait HandlesAccess
{
    public function name() : string {
        return $this->name;
    }

    public function firstRole() : MessageRole {
        return $this->messages()->firstRole();
    }

    public function lastRole() : MessageRole {
        return $this->messages()->lastRole();
    }

    public function isEmpty() : bool {
        return $this->messages()->isEmpty();
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }
}

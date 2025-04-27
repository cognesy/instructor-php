<?php
namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Utils\Messages\Enums\MessageRole;
use Cognesy\Utils\Messages\Messages;

trait HandlesAccess
{
    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }

    public function firstRole() : MessageRole {
        return $this->messages->firstRole();
    }

    public function lastRole() : MessageRole {
        return $this->messages->lastRole();
    }

    public function isEmpty() : bool {
        return $this->messages->isEmpty();
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }

    public function hasComposites() : bool {
        return $this->messages->hasComposites();
    }

    public function isTemplate() : bool {
        return $this->isTemplate;
    }

    public function messages() : Messages {
        return match(true) {
            $this->isEmpty() => $this->messages,
            default => $this->messages
                ->prependMessages($this->header)
                ->appendMessages($this->footer),
        };
    }
}

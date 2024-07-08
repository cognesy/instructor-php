<?php
namespace Cognesy\Instructor\Extras\Module\Core\Traits\ModuleCall;

trait HandlesErrors
{
    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }
}
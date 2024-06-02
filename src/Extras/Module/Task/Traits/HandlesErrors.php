<?php

namespace Cognesy\Instructor\Extras\Module\Task\Traits;

trait HandlesErrors
{
    private array $errors = [];

    public function hasErrors(): bool {
        return count($this->errors) > 0;
    }

    public function errors(): array {
        return $this->errors;
    }

    public function addError(string $message, array $context): void {
        $this->errors[] = ['message' => $message, 'context' => $context];
    }
}
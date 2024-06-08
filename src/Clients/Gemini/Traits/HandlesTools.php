<?php

namespace Cognesy\Instructor\Clients\Gemini\Traits;

trait HandlesTools
{
    public function tools(): array {
        return $this->tools;
    }

    public function getToolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'auto';
    }
}
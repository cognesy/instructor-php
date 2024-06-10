<?php

namespace Cognesy\Instructor\Clients\Gemini\Traits;

trait HandlesRequestBody
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

    protected function getResponseFormat(): array {
        return [];
    }

    protected function getResponseSchema(): array {
        return [];
    }
}
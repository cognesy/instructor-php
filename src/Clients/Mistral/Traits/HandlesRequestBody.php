<?php

namespace Cognesy\Instructor\Clients\Mistral\Traits;

trait HandlesRequestBody
{
    public function tools() : array {
        return $this->tools;
    }

    public function getToolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'any';
    }

    protected function getResponseFormat(): array {
        return ['type' => 'json_object'];
    }

    protected function getResponseSchema(): array {
        return [];
    }
}

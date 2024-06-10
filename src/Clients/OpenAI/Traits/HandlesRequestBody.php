<?php

namespace Cognesy\Instructor\Clients\OpenAI\Traits;

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

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat['format'] ?? [];
    }
}
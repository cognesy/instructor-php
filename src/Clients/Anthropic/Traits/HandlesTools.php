<?php

namespace Cognesy\Instructor\Clients\Anthropic\Traits;

trait HandlesTools
{
    protected function getToolSchema(): array {
        return $this->getResponseSchema() ?? $this->tools[0]['function']['parameters'] ?? [];
    }

    public function getToolChoice(): string|array {
        return '';
    }

    public function tools(): array {
        if (empty($this->tools)) {
            return [];
        }
        return $this->tools;
    }
}
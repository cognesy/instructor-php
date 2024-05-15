<?php

namespace Cognesy\Instructor\Clients\Anthropic\Traits;

trait HandlesResponseFormat {
    protected function getResponseFormat(): array {
        return [];
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }
}
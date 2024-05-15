<?php

namespace Cognesy\Instructor\Clients\OpenAI\Traits;

trait HandlesResponseFormat
{
    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat['format'] ?? [];
    }
}
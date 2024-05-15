<?php

namespace Cognesy\Instructor\Clients\Mistral\Traits;

trait HandlesResponseFormat
{
    protected function getResponseFormat(): array {
        return ['type' => 'json_object'];
    }

    protected function getResponseSchema(): array {
        return [];
    }
}

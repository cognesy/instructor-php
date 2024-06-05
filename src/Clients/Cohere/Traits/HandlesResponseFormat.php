<?php

namespace Cognesy\Instructor\Clients\Cohere\Traits;

trait HandlesResponseFormat
{
    protected function getResponseFormat(): array {
        return [];
    }

    protected function getResponseSchema(): array {
        return [];
    }
}
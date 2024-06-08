<?php

namespace Cognesy\Instructor\Clients\Gemini\Traits;

trait HandlesStreamData
{
    protected function isDone(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getData(string $data): string {
        return trim($data);
    }
}

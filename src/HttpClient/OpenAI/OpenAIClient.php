<?php

namespace Cognesy\Instructor\HttpClient\OpenAI;

use Cognesy\Instructor\HttpClient\LLMClient;

class OpenAIClient extends LLMClient
{
    protected function isDone(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }
}

<?php
namespace Cognesy\Instructor\Clients\OpenRouter\JsonCompletion;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiJsonCompletionRequest;

class JsonCompletionRequest extends ApiJsonCompletionRequest
{
    protected string $endpoint = '/chat/completions';

    public function getEndpoint(): string {
        return $this->endpoint;
    }
}
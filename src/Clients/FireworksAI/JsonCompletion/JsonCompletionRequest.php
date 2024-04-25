<?php
namespace Cognesy\Instructor\Clients\FireworksAI\JsonCompletion;

use Cognesy\Instructor\ApiClient\Requests\ApiJsonCompletionRequest;

class JsonCompletionRequest extends ApiJsonCompletionRequest
{
    protected string $endpoint = '/chat/completions';
}
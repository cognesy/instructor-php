<?php
namespace Cognesy\Instructor\Clients\Anyscale\JsonCompletion;

use Cognesy\Instructor\ApiClient\Requests\ApiJsonCompletionRequest;

class JsonCompletionRequest extends ApiJsonCompletionRequest
{
    protected string $endpoint = '/chat/completions';
}
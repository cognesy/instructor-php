<?php
namespace Cognesy\Instructor\Clients\OpenRouter\ChatCompletion;

use Cognesy\Instructor\ApiClient\Requests\ApiChatCompletionRequest;

class ChatCompletionRequest extends ApiChatCompletionRequest
{
    protected string $endpoint = '/chat/completions';
}
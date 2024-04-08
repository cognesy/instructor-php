<?php
namespace Cognesy\Instructor\Clients\Groq\ChatCompletion;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiChatCompletionRequest;

class ChatCompletionRequest extends ApiChatCompletionRequest
{
    protected string $endpoint = '/chat/completions';
}
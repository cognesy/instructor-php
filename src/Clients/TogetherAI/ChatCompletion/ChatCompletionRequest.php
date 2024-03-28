<?php
namespace Cognesy\Instructor\Clients\TogetherAI\ChatCompletion;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiChatCompletionRequest;

class ChatCompletionRequest extends ApiChatCompletionRequest
{
    protected string $endpoint = '/chat/completions';
}
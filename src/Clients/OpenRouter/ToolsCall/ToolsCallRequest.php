<?php
namespace Cognesy\Instructor\Clients\OpenRouter\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiToolsCallRequest;

class ToolsCallRequest extends ApiToolsCallRequest
{
    protected string $endpoint = '/chat/completions';
}

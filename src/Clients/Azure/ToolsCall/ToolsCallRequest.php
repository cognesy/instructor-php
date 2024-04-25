<?php
namespace Cognesy\Instructor\Clients\Azure\ToolsCall;

use Cognesy\Instructor\ApiClient\Requests\ApiToolsCallRequest;

class ToolsCallRequest extends ApiToolsCallRequest
{
    protected string $endpoint = '/chat/completions';
}

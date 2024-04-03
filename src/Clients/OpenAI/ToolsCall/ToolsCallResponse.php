<?php
namespace Cognesy\Instructor\Clients\OpenAI\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Saloon\Http\Response;

class ToolsCallResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        $functionName = $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '';
        return new self($content, $decoded, $functionName);
    }
}
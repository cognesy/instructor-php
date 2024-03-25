<?php
namespace Cognesy\Instructor\ApiClient\OpenAI\ToolsCall;

use Cognesy\Instructor\ApiClient\JsonResponse;
use Saloon\Http\Response;

class ToolsCallResponse extends JsonResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        $functionName = $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? null;
        return new self($content, $functionName, $decoded);
    }
}
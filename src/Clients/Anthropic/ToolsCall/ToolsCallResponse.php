<?php
namespace Cognesy\Instructor\Clients\Anthropic\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Saloon\Http\Response;

class ToolsCallResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response, true);
        $content = $decoded['content'][0]['text'] ?? '';
        $functionName = '';
        return new self($content, $decoded, $functionName);
    }
}
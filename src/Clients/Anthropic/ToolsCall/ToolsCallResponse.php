<?php
namespace Cognesy\Instructor\Clients\Anthropic\ToolsCall;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

//use Cognesy\Instructor\Utils\XmlExtractor;

class ToolsCallResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = Json::parse($response);
        $content = $decoded['content'][0]['text'] ?? '';
        //[$functionName, $args] = (new XmlExtractor)->extractToolCalls($content);
        //return new self($args, $decoded, $functionName);
        $finishReason = $decoded['stop_reason'] ?? '';
        return new self(
            content: $content,
            responseData: $decoded,
            functionName: '',
            finishReason: $finishReason,
            toolCalls: null
        );
    }
}
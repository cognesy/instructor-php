<?php
namespace Cognesy\Instructor\Clients\Azure\ChatCompletion;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ChatCompletionResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = Json::parse($response->body());
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        return new self(
            content: $content,
            responseData: $decoded,
            functionName: '',
            finishReason: $finishReason,
            toolCalls: null
        );
    }
}

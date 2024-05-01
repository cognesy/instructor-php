<?php

namespace Cognesy\Instructor\Clients\Anthropic\ChatCompletion;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Utils\Json;
use JetBrains\PhpStorm\Deprecated;
use Saloon\Http\Response;

#[Deprecated]
class ChatCompletionResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = Json::parse($response->body());
        $content = $decoded['content'][0]['text'] ?? '';
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
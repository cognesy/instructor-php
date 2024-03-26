<?php

namespace Cognesy\Instructor\Clients\Anthropic\ChatCompletion;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Saloon\Http\Response;

class ChatCompletionResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response->body(), true);
        $content = $decoded['content'][0]['text'] ?? '';
        return new self($content, $decoded, '');
    }
}
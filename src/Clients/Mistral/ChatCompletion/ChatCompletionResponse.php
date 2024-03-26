<?php
namespace Cognesy\Instructor\Clients\Mistral\ChatCompletion;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Saloon\Http\Response;

class ChatCompletionResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response->body(), true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        return new self($content, $decoded, '');
    }
}

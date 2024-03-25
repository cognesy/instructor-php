<?php
namespace Cognesy\Instructor\ApiClient\Mistral\ChatCompletion;

use Cognesy\Instructor\ApiClient\JsonResponse;
use Saloon\Http\Response;

class ChatCompletionResponse extends JsonResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response->body(), true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        return new self($content, null, $decoded);
    }
}

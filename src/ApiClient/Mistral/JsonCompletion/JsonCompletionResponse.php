<?php
namespace Cognesy\Instructor\ApiClient\Mistral\JsonCompletion;

use Cognesy\Instructor\ApiClient\JsonResponse;
use Saloon\Http\Response;

class JsonCompletionResponse extends JsonResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = json_decode($response->body(), true);
        $content = $decoded['choices'][0]['text'] ?? '';
        return new self($content, null, $decoded);
    }
}

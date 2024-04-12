<?php
namespace Cognesy\Instructor\Clients\FireworksAI\ChatCompletion;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ChatCompletionResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = Json::parse($response->body());
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        return new self($content, $decoded, '');
    }
}

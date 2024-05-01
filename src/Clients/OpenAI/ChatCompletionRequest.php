<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\Requests\ApiChatCompletionRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ChatCompletionRequest extends ApiChatCompletionRequest
{
    protected string $endpoint = '/chat/completions';

    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            functionName: '',
            finishReason: $finishReason,
            toolCalls: null
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
        return new PartialApiResponse($delta, $decoded);
    }
}
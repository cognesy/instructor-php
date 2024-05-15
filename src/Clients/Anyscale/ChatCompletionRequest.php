<?php
namespace Cognesy\Instructor\Clients\Anyscale;

use Cognesy\Instructor\ApiClient\Requests\ApiChatCompletionRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use JetBrains\PhpStorm\Deprecated;
use Saloon\Http\Response;

#[Deprecated]
class ChatCompletionRequest extends ApiChatCompletionRequest
{
//    protected string $defaultEndpoint = '/chat/completions';

//    public function toApiResponse(Response $response): ApiResponse {
//        $decoded = Json::parse($response->body());
//        $content = $decoded['choices'][0]['message']['content'] ?? '';
//        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
//        return new ApiResponse(
//            content: $content,
//            responseData: $decoded,
//            toolName: '',
//            finishReason: $finishReason,
//            toolCalls: null,
//            inputTokens: 0,
//            outputTokens: 0,
//        );
//    }
//
//    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
//        $decoded = Json::parse($partialData, default: []);
//        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
//        return new PartialApiResponse(
//            delta: $delta,
//            responseData: $decoded,
//            inputTokens: 0,
//            outputTokens: 0,
//        );
//    }
}

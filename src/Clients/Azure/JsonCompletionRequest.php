<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\Requests\ApiJsonCompletionRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class JsonCompletionRequest extends ApiJsonCompletionRequest
{
    protected string $endpoint = '/chat/completions';

    public function __construct(
        public string|array  $messages = [],
        public array  $responseFormat = [],
        public string $model = '',
        public array  $options = [],
    ) {
        parent::__construct(
            $messages,
            $responseFormat,
            $model,
            $options,
        );
    }

    protected function getResponseFormat(): array {
        return ['type' => 'json_object'];
    }

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
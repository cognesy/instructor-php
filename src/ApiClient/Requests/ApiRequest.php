<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesApiRequestContext;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Traits\HandlesApiCaching;
use Cognesy\Instructor\Utils\Json;
use Exception;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody;
    use HandlesApiCaching;
    use HandlesApiRequestContext;

    protected string $defaultEndpoint = '/chat/completions';
    protected Method $method = Method::POST;
    protected bool $debug = false;

    public function __construct(
        public string|array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public string|array $responseFormat = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        $this->messages = $this->normalizeMessages($messages);

        $this->debug = $this->options['debug'] ?? false;
        unset($this->options['debug']);

        $this->cachingEnabled = $this->options['cache'] ?? false;
        unset($this->options['cache']);

        if ($this->cachingEnabled) {
            if ($this->isStreamed()) {
                throw new \Exception('Cannot use cache with streamed requests');
            }
        }
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function isDebug(): bool {
        return $this->debug;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint ?: $this->defaultEndpoint;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    protected function appendInstructions(array $messages, string $prompt, array $jsonSchema) : array {
        if (empty($messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }
        $lastIndex = count($messages) - 1;
        if (!empty($prompt)) {
            $messages[$lastIndex]['content'] .= $prompt;
        }
        if (!empty($jsonSchema)) {
            $messages[$lastIndex]['content'] .= Json::encode($jsonSchema);
        }
        return $messages;
    }

    protected function defaultBody(): array {
        return array_filter(array_merge([
            'messages' => $this->messages(),
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->getToolChoice(),
            'response_format' => $this->getResponseFormat(),
        ], $this->options));
    }

    protected function getToolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'auto';
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat['format'] ?? [];
    }

    protected function messages(): array {
        return $this->messages;
    }

    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        $toolName = $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '';
        $inputTokens = $decoded['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $decoded['usage']['completion_tokens'] ?? 0;
        $contentMsg = $decoded['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        $content = match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            toolName: $toolName,
            finishReason: $finishReason,
            toolCalls: null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        $toolName = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
        $inputTokens = $decoded['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $decoded['usage']['completion_tokens'] ?? 0;
        $deltaContent = $decoded['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        $delta = match(true) {
            !empty($deltaContent) => $deltaContent,
            !empty($deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
        return new PartialApiResponse(
            delta: $delta,
            responseData: $decoded,
            toolName: $toolName,
            finishReason: $finishReason,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }
}
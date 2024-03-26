<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\Clients\OpenAI\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\Clients\OpenAI\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\Clients\OpenAI\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\Clients\OpenAI\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\Clients\OpenAI\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\Clients\OpenAI\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\Clients\OpenAI\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\Clients\OpenAI\ToolsCall\ToolsCallResponse;
use Cognesy\Instructor\Events\EventDispatcher;

class OpenAIClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
{
    public string $defaultModel = 'gpt-4-turbo-preview';

    public function __construct(
        protected $apiKey,
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
    ) {
        parent::__construct($events);
        $this->connector = new OpenAIConnector(
            $apiKey,
            $baseUri,
            $connectTimeout,
            $requestTimeout,
            $metadata,
        );
    }

    public function makeRequest(array $payload) : ApiRequest {
        $hasTools = $payload['tools'] ?? false;
        $responseFormat = $payload['response_format'] ?? false;
        $isJsonFormat = $responseFormat && $responseFormat['type'] === 'json_object';
        return match(true) {
            $hasTools => ToolsCallRequest::fromArray($payload),
            $isJsonFormat => JsonCompletionRequest::fromArray($payload),
            default => ChatCompletionRequest::fromArray($payload),
        };
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(array $messages, string $model, array $options = []): static {
        $model = $model ?: $this->defaultModel;
        $this->request = new ChatCompletionRequest($messages, $model, $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialChatCompletionResponse::class;
        } else {
            $this->responseClass = ChatCompletionResponse::class;
        }
        return $this;
    }

    public function jsonCompletion(array $messages, string $model, array $options = []): static {
        $model = $model ?: $this->defaultModel;
        $this->request = new JsonCompletionRequest($messages, $model, $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialJsonCompletionResponse::class;
        } else {
            $this->responseClass = JsonCompletionResponse::class;
        }
        return $this;
    }

    public function toolsCall(array $messages, string $model, array $tools, array $toolChoice, array $options = []): static {
        $model = $model ?: $this->defaultModel;
        $this->request = new ToolsCallRequest($messages, $model, $tools, $toolChoice, $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialToolsCallResponse::class;
        } else {
            $this->responseClass = ToolsCallResponse::class;
        }
        return $this;
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function isDone(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }
}

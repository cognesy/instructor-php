<?php
namespace Cognesy\Instructor\Clients\TogetherAI;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Clients\TogetherAI\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\Clients\TogetherAI\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\Clients\TogetherAI\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\Clients\TogetherAI\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\Clients\TogetherAI\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\Clients\TogetherAI\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\Clients\TogetherAI\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\Clients\TogetherAI\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\Clients\TogetherAI\ToolsCall\ToolsCallResponse;
use Cognesy\Instructor\Events\EventDispatcher;

class TogetherAIClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
{
    public string $defaultModel = 'mistralai/Mixtral-8x7B-Instruct-v0.1';

    public function __construct(
        protected $apiKey = '',
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);
        $this->connector = $connector ?? new TogetherAIConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        );
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(array $messages, string $model = '', array $options = []): static {
        $this->request = new ChatCompletionRequest($messages, $this->getModel($model), $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialChatCompletionResponse::class;
        } else {
            $this->responseClass = ChatCompletionResponse::class;
        }
        return $this;
    }

    public function jsonCompletion(array $messages, array $responseFormat, string $model = '', array $options = []): static {
        $this->request = new JsonCompletionRequest($messages, $responseFormat, $this->getModel($model), $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialJsonCompletionResponse::class;
        } else {
            $this->responseClass = JsonCompletionResponse::class;
        }
        return $this;
    }

    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static {
        $this->request = new ToolsCallRequest($messages, $tools, $toolChoice, $this->getModel($model), $options);
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

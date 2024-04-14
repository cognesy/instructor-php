<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
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
        protected $apiKey = '',
        protected $baseUri = '',
        protected $organization = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);
        $this->connector = $connector ?? new OpenAIConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            organization: $organization,
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

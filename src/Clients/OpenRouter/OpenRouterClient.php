<?php
namespace Cognesy\Instructor\Clients\OpenRouter;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenRouter\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\Clients\OpenRouter\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\Clients\OpenRouter\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\Clients\OpenRouter\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\Clients\OpenRouter\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\Clients\OpenRouter\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\Clients\OpenRouter\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\Clients\OpenRouter\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\Clients\OpenRouter\ToolsCall\ToolsCallResponse;
use Cognesy\Instructor\Events\EventDispatcher;

class OpenRouterClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
{
    public string $defaultModel = 'gpt-3.5-turbo';

    public function __construct(
        protected $apiKey,
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
    ) {
        parent::__construct($events);
        $this->connector = new OpenRouterConnector(
            $apiKey,
            $baseUri,
            $connectTimeout,
            $requestTimeout,
            $metadata,
        );
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

    public function toolsCall(array $messages, string $model, array $tools, array $toolChoice = [], array $options = []): static {
        $model = $model ?: $this->defaultModel;
        $this->request = new ToolsCallRequest($messages, $model, $tools, $options);
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

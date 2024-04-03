<?php
namespace Cognesy\Instructor\Clients\FireworksAI;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Clients\FireworksAI\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\Clients\FireworksAI\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\Clients\FireworksAI\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\Clients\FireworksAI\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\Clients\FireworksAI\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\Clients\FireworksAI\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\Clients\FireworksAI\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\Clients\FireworksAI\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\Clients\FireworksAI\ToolsCall\ToolsCallResponse;
use Cognesy\Instructor\Events\EventDispatcher;

class FireworksAIClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
{
    public string $defaultModel = 'accounts/fireworks/models/mixtral-8x7b-instruct';

    public function __construct(
        protected $apiKey,
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
    ) {
        parent::__construct($events);
        $this->connector = new FireworksAIConnector(
            $apiKey,
            $baseUri,
            $connectTimeout,
            $requestTimeout,
            $metadata,
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

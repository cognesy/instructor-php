<?php
namespace Cognesy\Instructor\Clients\Anyscale;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Clients\Anyscale\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\Clients\Anyscale\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\Clients\Anyscale\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\Clients\Anyscale\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\Clients\Anyscale\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\Clients\Anyscale\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\Clients\Anyscale\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\Clients\Anyscale\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\Clients\Anyscale\ToolsCall\ToolsCallResponse;
use Cognesy\Instructor\Events\EventDispatcher;

class AnyscaleClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
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
        $this->connector = $connector ?? new AnyscaleConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        );
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(string|array $messages, string $model = '', array $options = []): static {
        $this->request = new ChatCompletionRequest($messages, $this->getModel($model), $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialChatCompletionResponse::class;
        } else {
            $this->responseClass = ChatCompletionResponse::class;
        }
        return $this;
    }

    public function jsonCompletion(string|array $messages, array $responseFormat, string $model = '', array $options = []): static {
        $this->request = new JsonCompletionRequest($messages, $responseFormat, $this->getModel($model), $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialJsonCompletionResponse::class;
        } else {
            $this->responseClass = JsonCompletionResponse::class;
        }
        return $this;
    }

    public function toolsCall(string|array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static {
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

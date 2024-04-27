<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Clients\Azure\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\Clients\Azure\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\Clients\Azure\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\Clients\Azure\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\Clients\Azure\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\Clients\Azure\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\Clients\Azure\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\Clients\Azure\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\Clients\Azure\ToolsCall\ToolsCallResponse;
use Cognesy\Instructor\Events\EventDispatcher;

class AzureClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
{
    public string $defaultModel = 'gpt-4-turbo-preview';
    public int $defaultMaxTokens = 256;

    public function __construct(
        protected string $apiKey = '',
        protected string $resourceName = '',
        protected string $deploymentId = '',
        protected string $apiVersion = '',
        protected string $baseUri = '',
        protected int $connectTimeout = 3,
        protected int $requestTimeout = 30,
        protected array $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);
        $this->withConnector($connector ?? new AzureConnector(
            apiKey: $apiKey,
            resourceName: $resourceName,
            deploymentId: $deploymentId,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
        $this->queryParams = ['api-version' => $apiVersion];
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(array $messages, string $model = '', array $options = []): static {
        $this->request = $this->makeRequest(
            ChatCompletionRequest::class,
            [$messages, $this->getModel($model), $options]
        );
        $this->partialResponseClass = ChatCompletionResponse::class;
        $this->responseClass = PartialChatCompletionResponse::class;
        return $this;
    }

    public function jsonCompletion(array $messages, array $responseFormat, string $model = '', array $options = []): static {
        $this->request = $this->makeRequest(
            JsonCompletionRequest::class,
            [$messages, $responseFormat, $this->getModel($model), $options]
        );
        $this->partialResponseClass = PartialJsonCompletionResponse::class;
        $this->responseClass = JsonCompletionResponse::class;
        return $this;
    }

    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static {
        $this->request = $this->makeRequest(
            ToolsCallRequest::class,
            [$messages, $tools, $toolChoice, $this->getModel($model), $options]
        );
        $this->partialResponseClass = PartialToolsCallResponse::class;
        $this->responseClass = ToolsCallResponse::class;
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

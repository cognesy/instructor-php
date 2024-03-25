<?php
namespace Cognesy\Instructor\ApiClient\OpenRouter;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\OpenRouter\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\ApiClient\OpenRouter\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\ApiClient\OpenRouter\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\ApiClient\OpenRouter\JsonCompletion\JsonCompletionRequest;
use Cognesy\Instructor\ApiClient\OpenRouter\JsonCompletion\JsonCompletionResponse;
use Cognesy\Instructor\ApiClient\OpenRouter\JsonCompletion\PartialJsonCompletionResponse;
use Cognesy\Instructor\ApiClient\OpenRouter\ToolsCall\PartialToolsCallResponse;
use Cognesy\Instructor\ApiClient\OpenRouter\ToolsCall\ToolsCallRequest;
use Cognesy\Instructor\ApiClient\OpenRouter\ToolsCall\ToolsCallResponse;

class OpenRouterClient extends ApiClient
{
    public function __construct(
        protected $apiKey,
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
    ) {
        parent::__construct();
        $this->connector = new OpenRouterConnector(
            $apiKey,
            $baseUri,
            $connectTimeout,
            $requestTimeout,
            $metadata,
        );
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(array $messages, string $model, array $options = []): self {
        $this->request = new ChatCompletionRequest($messages, $model, $options);
        $this->responseClass = ChatCompletionResponse::class;
        return $this;
    }

    public function jsonCompletion(array $messages, string $model, array $options = []): self {
        $this->request = new JsonCompletionRequest($messages, $model, $options);
        $this->responseClass = JsonCompletionResponse::class;
        return $this;
    }

    public function toolsCall(array $messages, string $model, array $tools, array $options = []): self {
        $this->request = new ToolsCallRequest($messages, $model, $tools, $options);
        $this->responseClass = ToolsCallResponse::class;
        return $this;
    }

    public function chatCompletionStream(array $messages, string $model, array $options = []): self {
        $this->request = new ChatCompletionRequest($messages, $model, $options);
        $this->responseClass = PartialChatCompletionResponse::class;
        return $this;
    }

    public function jsonCompletionStream(array $messages, string $model, array $options = []): self {
        $this->request = new JsonCompletionRequest($messages, $model, $options);
        $this->responseClass = PartialJsonCompletionResponse::class;
        return $this;
    }

    public function toolsCallStream(array $messages, string $model, array $tools, array $options = []): self {
        $this->request = new ToolsCallRequest($messages, $model, $tools, $options);
        $this->responseClass = PartialToolsCallResponse::class;
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

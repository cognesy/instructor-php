<?php

namespace Cognesy\Instructor\ApiClient\Anthropic;

use Cognesy\Instructor\ApiClient\Anthropic\ChatCompletion\ChatCompletionRequest;
use Cognesy\Instructor\ApiClient\Anthropic\ChatCompletion\ChatCompletionResponse;
use Cognesy\Instructor\ApiClient\Anthropic\ChatCompletion\PartialChatCompletionResponse;
use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\JsonResponse;

class AnthropicClient extends ApiClient
{
    public function __construct(
        protected $apiKey,
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
    ) {
        parent::__construct();
        $this->connector = new AnthropicConnector(
            $apiKey,
            $baseUri,
            $connectTimeout,
            $requestTimeout,
            $metadata,
        );
    }

    /// PUBLIC API ////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(array $messages, string $model, array $options = []): self {
        $this->request = new ChatCompletionRequest($messages, $model, $options);
        if ($this->request->isStreamed()) {
            $this->responseClass = PartialChatCompletionResponse::class;
        } else {
            $this->responseClass = ChatCompletionResponse::class;
        }
        return $this;
    }

    /// INTERNAL //////////////////////////////////////////////////////////////////////////////////

    protected function isDone(string $data): bool {
        return $data === 'event: message_stop';
    }

    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }
}
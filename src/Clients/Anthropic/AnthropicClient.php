<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Events\EventDispatcher;

class AnthropicClient extends ApiClient implements CanCallChatCompletion, CanCallJsonCompletion, CanCallTools
{
    public string $defaultModel = 'claude-3-haiku-20240307';
    public int $defaultMaxTokens = 256;

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
        $this->withConnector($connector ?? new AnthropicConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }

    /// PUBLIC API ////////////////////////////////////////////////////////////////////////////////

    public function chatCompletion(array $messages, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->request = $this->makeRequest(
            ChatCompletionRequest::class,
            [$messages, $this->getModel($model), $options]
        );
        return $this;
    }

    public function jsonCompletion(array $messages, array $responseFormat, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->request = $this->makeRequest(
            JsonCompletionRequest::class,
            [$messages, $responseFormat, $this->getModel($model), $options]
        );
        return $this;
    }

    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->request = $this->makeRequest(
            ToolsCallRequest::class,
            [$messages, $tools, $toolChoice, $this->getModel($model), $options]
        );
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
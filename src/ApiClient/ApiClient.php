<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;

abstract class ApiClient implements CanCallApi
{
    use HandlesEvents;
    use HandlesEventListeners;

    use Traits\HandlesApiConnector;
    use Traits\HandlesApiRequest;
    use Traits\HandlesApiRequestFactory;
    use Traits\HandlesApiResponse;
    use Traits\HandlesAsyncApiResponse;
    use Traits\HandlesDefaultModel;
    use Traits\HandlesQueryParams;
    use Traits\HandlesStreamApiResponse;
    use Traits\ReadsStreamResponse;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->withEventDispatcher($events ?? new EventDispatcher('api-client'));
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function request(
        array $messages, array $tools = [], array $toolChoice = [], array $responseFormat = [], string $model = '', array $options = []
    ): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $mode = match(true) {
            !empty($tools) => Mode::Tools,
            !empty($responseFormat) => Mode::Json,
            default => Mode::MdJson,
        };
        $this->apiRequest = $this->apiRequestFactory->makeRequest(
            requestClass: $this->getModeRequestClass($mode),
            messages: $messages,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $responseFormat,
            model: $this->getModel($model),
            options: $options
        );
        return $this;
    }

    public function chatCompletion(array $messages, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->apiRequest = $this->apiRequestFactory->makeChatCompletionRequest(
            requestClass: $this->getModeRequestClass(Mode::MdJson),
            messages: $messages,
            model: $this->getModel($model),
            options: $options
        );
        return $this;
    }

    public function jsonCompletion(array $messages, array $responseFormat, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->apiRequest = $this->apiRequestFactory->makeJsonCompletionRequest(
            requestClass: $this->getModeRequestClass(Mode::Json),
            messages: $messages,
            responseFormat: $responseFormat,
            model: $this->getModel($model),
            options: $options
        );
        return $this;
    }

    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->apiRequest = $this->apiRequestFactory->makeToolsCallRequest(
            requestClass: $this->getModeRequestClass(Mode::Tools),
            messages: $messages,
            tools: $tools,
            toolChoice: $toolChoice,
            model: $this->getModel($model),
            options: $options
        );
        return $this;
    }

    abstract public function getModeRequestClass(Mode $mode) : string;
}

<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ModelFactory;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;

abstract class ApiClient implements CanCallApi
{
    use HandlesEvents;
    use HandlesEventListeners;
    use Traits\HandlesDefaultModel;
    use Traits\HandlesApiResponse;
    use Traits\HandlesAsyncApiResponse;
    use Traits\HandlesStreamApiResponse;
    use Traits\HandlesApiRequestFactory;
    use Traits\HandlesModelParams;

    public function __construct(
        EventDispatcher $events = null,
        ModelFactory $modelFactory = null,
    ) {
        $this->withEventDispatcher($events ?? new EventDispatcher());
        $this->withModelFactory($modelFactory ?? new ModelFactory());
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function createApiRequest(Request $request) : ApiRequest {
        if (empty($request->model())) {
            $request->withModel($this->defaultModel());
        }
        if (empty($request->option('max_tokens'))) {
            $request->setOption('max_tokens', $this->defaultMaxTokens);
        }
        $requestClass = $this->getModeRequestClass($request->mode());
        return $this->apiRequestFactory->fromRequest($requestClass, $request);
    }

    public function request(array $messages, array $tools = [], array $toolChoice = [], array $responseFormat = [], string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->apiRequest = $this->apiRequestFactory->makeRequest(
            requestClass: ApiRequest::class,
            prompt: '',
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
            prompt: '',
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
            prompt: '',
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
            prompt: '',
            messages: $messages,
            tools: $tools,
            toolChoice: $toolChoice,
            model: $this->getModel($model),
            options: $options
        );
        return $this;
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    abstract protected function getModeRequestClass(Mode $mode) : string;
}

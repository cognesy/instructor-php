<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
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

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->withEventDispatcher($events ?? new EventDispatcher());
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function createApiRequest(Request $request, ResponseModel $responseModel) : ApiRequest {
        $tryRequest = clone $request;
        if (empty($tryRequest->model)) {
            $tryRequest->model = $this->getDefaultModel();
        }
        if (!isset($tryRequest->options['max_tokens'])) {
            $tryRequest->options['max_tokens'] = $this->defaultMaxTokens;
        }
        $requestClass = $this->getModeRequestClass($tryRequest->mode);
        return $this->apiRequestFactory->fromRequest($requestClass, $tryRequest, $responseModel);
    }

    public function chatCompletion(array $messages, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->request = $this->apiRequestFactory->makeChatCompletionRequest(
            $this->getModeRequestClass(Mode::MdJson),
            $messages, $this->getModel($model), $options
        );
        return $this;
    }

    public function jsonCompletion(array $messages, array $responseFormat, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->request = $this->apiRequestFactory->makeJsonCompletionRequest(
            $this->getModeRequestClass(Mode::Json),
            $messages, $responseFormat, $this->getModel($model), $options
        );
        return $this;
    }

    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->defaultMaxTokens;
        }
        $this->request = $this->apiRequestFactory->makeToolsCallRequest(
            $this->getModeRequestClass(Mode::Tools),
            $messages, $tools, $toolChoice, $this->getModel($model), $options
        );
        return $this;
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    abstract protected function getModeRequestClass(Mode $mode) : string;
}

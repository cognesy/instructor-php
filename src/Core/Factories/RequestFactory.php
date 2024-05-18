<?php

namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;

class RequestFactory
{
    public function __construct(
        protected ApiClientFactory $clientFactory,
        protected ResponseModelFactory $responseModelFactory,
        protected ModelFactory $modelFactory,
        protected ApiRequestFactory $apiRequestFactory,
        protected EventDispatcher $events,
    ) {}

    public function create(
        string|array $messages,
        string|object|array $responseModel,
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        array $examples = [],
        string $toolName = '',
        string $toolDescription = '',
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
    ) : Request {
        $request = new Request(
            messages: $messages,
            responseModel: $responseModel,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            examples: $examples,
            toolName: $toolName,
            toolDescription: $toolDescription,
            prompt: $prompt,
            retryPrompt: $retryPrompt,
            mode: $mode,
            client: $this->clientFactory->getDefault(),
            modelFactory: $this->modelFactory,
            responseModelFactory: $this->responseModelFactory,
            apiRequestFactory: $this->apiRequestFactory,
        );
        return $request;
    }

    public function fromRequest(Request $request) : Request {
        // make sure the request has a client
        if ($request->client() === null) {
            $request->withClient(
                $this->clientFactory->getDefault()
            );
        }
        // make sure the request has a response model
        if ($request->responseModel() === null) {
            $request->withResponseModel(
                $this->responseModelFactory->fromAny(
                    requestedModel: $request->requestedSchema(),
                    toolName: $request->toolName(),
                    toolDescription: $request->toolDescription()
                )
            );
        }
        // make sure the request has APIRequestFactory
        if ($request->apiRequestFactory() === null) {
            $request->withApiRequestFactory($this->apiRequestFactory);
        }
        return $request;
    }
}

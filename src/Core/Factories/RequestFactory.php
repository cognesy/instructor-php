<?php

namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
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
        protected ApiRequestConfig $requestConfig,
        protected EventDispatcher $events,
    ) {}

    public function create(
        string|array|object $input = [],
        string|array $messages = [],
        string|object|array $responseModel = [],
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
        return new Request(
            messages: $messages,
            input: $input,
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
            clientFactory: $this->clientFactory,
            apiRequestFactory: $this->apiRequestFactory,
            requestConfig: $this->requestConfig,
        );
    }
}

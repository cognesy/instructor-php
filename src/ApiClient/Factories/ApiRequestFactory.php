<?php

namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class ApiRequestFactory
{
    public function __construct(
        private ApiRequestContext $context,
    ) {}

    /**
     * @param class-string $requestClass
    */
    public function makeRequest(
        string $requestClass,
        array $messages,
        array $tools,
        array $toolChoice,
        array $responseFormat,
        string $model = '',
        array $options = []
    ): ApiRequest {
        /** @var ApiRequest $apiRequest */
        $apiRequest = new $requestClass(...[
            'messages' => $messages,
            'tools' => $tools,
            'toolChoice' => $toolChoice,
            'responseFormat' => $responseFormat,
            'model' => $model,
            'options' => $options,
        ]);
        $apiRequest->withContext($this->context);
        return $apiRequest;
    }

    public function makeChatCompletionRequest(
        string $requestClass,
        array $messages,
        string $model = '',
        array $options = []
    ): ApiRequest {
        return $this->fromClass(
            requestClass: $requestClass,
            args: [
                'messages' => $messages,
                'tools' => [],
                'toolChoice' => [],
                'responseFormat' => [],
                'model' => $model,
                'options' => $options,
            ]
        );
    }

    public function makeJsonCompletionRequest(
        string $requestClass,
        array $messages,
        array $responseFormat,
        string $model = '',
        array $options = []
    ): ApiRequest {
        return $this->fromClass(
            requestClass: $requestClass,
            args: [
                'messages' => $messages,
                'tools' => [],
                'toolChoice' => [],
                'responseFormat' => $responseFormat,
                'model' => $model,
                'options' => $options,
            ]
        );
    }

    public function makeToolsCallRequest(
        string $requestClass,
        array $messages,
        array $tools,
        array $toolChoice,
        string $model = '',
        array $options = []
    ): ApiRequest {
        return $this->fromClass(
            requestClass: $requestClass,
            args: [
                'messages' => $messages,
                'tools' => $tools,
                'toolChoice' => $toolChoice,
                'responseFormat' => [],
                'model' => $model,
                'options' => $options,
            ]
        );
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function fromClass(string $requestClass, array $args) : ApiRequest {
        /** @var ApiRequest $apiRequest */
        $apiRequest = new $requestClass(...$args);
        $apiRequest->withContext($this->context);
        return $apiRequest;
    }
}

<?php

namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Exception;

class ApiRequestFactory
{
    public function __construct(
        private ApiRequestContext $context,
    ) {}

    public function fromRequest(string $requestClass, Request $request, ResponseModel $responseModel) : ApiRequest {
        return match($request->mode) {
            Mode::MdJson => $this->toChatCompletionRequest($requestClass, $request, $responseModel),
            Mode::Json => $this->toJsonCompletionRequest($requestClass, $request, $responseModel),
            Mode::Tools => $this->toToolsCallRequest($requestClass, $request, $responseModel),
            default => throw new Exception('Unknown mode')
        };
    }

    public function makeChatCompletionRequest(string $requestClass, array $messages, string $model = '', array $options = []): ApiRequest {
        return $this->fromClass(
            $requestClass,
            [$messages, $model, $options]
        );
    }

    public function makeJsonCompletionRequest(string $requestClass, array $messages, array $responseFormat, string $model = '', array $options = []): ApiRequest {
        return $this->fromClass(
            $requestClass,
            [$messages, $responseFormat, $model, $options]
        );
    }

    public function makeToolsCallRequest(string $requestClass, array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): ApiRequest {
        return $this->fromClass(
            $requestClass,
            [$messages, $tools, $toolChoice, $model, $options]
        );
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function fromClass(string $requestClass, array $args) : ApiRequest {
        return (new $requestClass(...$args))->withContext($this->context);
    }

    protected function toChatCompletionRequest(string $requestClass, Request $request, ResponseModel $responseModel) : ApiRequest {
        return $this->makeChatCompletionRequest($requestClass,
            $request->appendInstructions($request->messages, $responseModel->jsonSchema),
            $request->model,
            $request->options,
        );
    }

    protected function toJsonCompletionRequest(string $requestClass, Request $request, ResponseModel $responseModel) : ApiRequest {
        return $this->makeJsonCompletionRequest(
            $requestClass,
            $request->messages,
            [
                'type' => 'json_object',
                'schema' => $responseModel->jsonSchema
            ],
            $request->model,
            $request->options,
        );
    }

    protected function toToolsCallRequest(string $requestClass, Request $request, ResponseModel $responseModel) : ApiRequest {
        return $this->makeToolsCallRequest(
            $requestClass,
            $request->messages,
            [$responseModel->toolCallSchema()],
            [
                'type' => 'function',
                'function' => ['name' => $responseModel->functionName]
            ],
            $request->model,
            $request->options,
        );
    }
}

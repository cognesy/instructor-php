<?php

namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Exception;

class ApiRequestFactory
{
    public function __construct(
        private ApiRequestContext $context,
    ) {}

    public function fromRequest(string $requestClass, Request $request) : ApiRequest {
        return match($request->mode) {
            Mode::MdJson => $this->toChatCompletionRequest($requestClass, $request),
            Mode::Json => $this->toJsonCompletionRequest($requestClass, $request),
            Mode::Tools => $this->toToolsCallRequest($requestClass, $request),
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
        return (new $requestClass(...$args))
            ->withContext($this->context);
    }

    protected function toChatCompletionRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeChatCompletionRequest($requestClass,
            $request->appendInstructions($request->messages, $request->jsonSchema()),
            $request->model,
            $request->options,
        );
    }

    protected function toJsonCompletionRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeJsonCompletionRequest(
            $requestClass,
            $request->messages,
            [
                'type' => 'json_object',
                'schema' => $request->jsonSchema()
            ],
            $request->model,
            $request->options,
        );
    }

    protected function toToolsCallRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeToolsCallRequest(
            $requestClass,
            $request->messages,
            [$request->toolCallSchema()],
            [
                'type' => 'function',
                'function' => ['name' => $request->functionName()]
            ],
            $request->model,
            $request->options,
        );
    }
}

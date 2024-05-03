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

    public function makeChatCompletionRequest(
        string $requestClass,
        string $prompt,
        array $messages,
        string $model = '',
        array $options = []
    ): ApiRequest {
        return $this->fromClass(
            $requestClass,
            $prompt,
            [$messages, $model, $options]
        );
    }

    public function makeJsonCompletionRequest(
        string $requestClass,
        string $prompt,
        array $messages,
        array $responseFormat,
        string $model = '',
        array $options = []
    ): ApiRequest {
        return $this->fromClass(
            $requestClass,
            $prompt,
            [$messages, $responseFormat, $model, $options]
        );
    }

    public function makeToolsCallRequest(
        string $requestClass,
        string $prompt,
        array $messages,
        array $tools,
        array $toolChoice,
        string $model = '',
        array $options = []
    ): ApiRequest {
        return $this->fromClass(
            $requestClass,
            $prompt,
            [$messages, $tools, $toolChoice, $model, $options]
        );
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function fromClass(string $requestClass, string $prompt, array $args) : ApiRequest {
        /** @var ApiRequest $apiRequest */
        $apiRequest = new $requestClass(...$args);
        $apiRequest->withPrompt($prompt);
        $apiRequest->withContext($this->context);
        return $apiRequest;
    }

    protected function toChatCompletionRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeChatCompletionRequest(
            $requestClass,
            $request->prompt(),
            $request->appendInstructions($request->messages, $request->jsonSchema()),
            $request->model,
            $request->options,
        );
    }

    protected function toJsonCompletionRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeJsonCompletionRequest(
            $requestClass,
            $request->prompt(),
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
            $request->prompt(),
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

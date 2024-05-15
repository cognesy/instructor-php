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
        return match ($request->mode()) {
            Mode::MdJson => $this->toChatCompletionRequest($requestClass, $request),
            Mode::Json => $this->toJsonCompletionRequest($requestClass, $request),
            Mode::Tools => $this->toToolsCallRequest($requestClass, $request),
            default => $this->toRequest($requestClass, $request),
        };
    }

//    public function newFromRequest(string $requestClass, Request $request) : ApiRequest {
//        /** @var ApiRequest $apiRequest */
//        $apiRequest = new $requestClass(...[
//            'messages' => $request->prependInstructions($request->messages(), $request->prompt(), $request->jsonSchema(), $request->examples()),
//            'tools' => [$request->toolCallSchema()],
//            'toolChoice' => [
//                'type' => 'function',
//                'function' => ['name' => $request->toolName()]
//            ],
//            'responseFormat' => [
//                'type' => 'json_object',
//                'schema' => $request->jsonSchema()
//            ],
//            'model' => $request->modelName(),
//            'options' => $request->options(),
//            //'endpoint' => '/chat/completions'
//        ]);
//        $apiRequest->withPrompt($request->prompt());
//        $apiRequest->withContext($this->context);
//        return $apiRequest;
//    }

    public function makeRequest(
        string $requestClass,
        string $prompt,
        array $messages,
        array $tools,
        array $toolChoice,
        array $responseFormat,
        string $model = '',
        array $options = []
    ): ApiRequest {
        $apiRequest = new $requestClass(...[
            'messages' => $messages,
            'tools' => $tools,
            'toolChoice' => $toolChoice,
            'responseFormat' => $responseFormat,
            'model' => $model,
            'options' => $options,
            //'endpoint' => '/chat/completions'
        ]);
        $apiRequest->withPrompt($prompt);
        $apiRequest->withContext($this->context);
        return $apiRequest;
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
            [
                'messages' => $messages,
                'tools' => [],
                'toolChoice' => [],
                'responseFormat' => [],
                'model' => $model,
                'options' => $options,
                //'endpoint' => '/chat/completions'
            ]
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
            [
                'messages' => $messages,
                'tools' => [],
                'toolChoice' => [],
                'responseFormat' => $responseFormat,
                'model' => $model,
                'options' => $options,
                //'endpoint' => '/chat/completions'
            ]
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
            [
                'messages' => $messages,
                'tools' => $tools,
                'toolChoice' => $toolChoice,
                'responseFormat' => [],
                'model' => $model,
                'options' => $options,
                //'endpoint' => '/chat/completions'
            ]
        );
    }

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function fromClass(string $requestClass, string $prompt, array $args) : ApiRequest {
        /** @var ApiRequest $apiRequest */
        $apiRequest = new $requestClass(...$args);
        $apiRequest->withContext($this->context);
        return $apiRequest;
    }

    protected function toRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeRequest(
            $requestClass,
            $request->prompt(),
            $request->prependInstructions($request->messages(), $request->prompt(), $request->jsonSchema(), $request->examples()),
            [$request->toolCallSchema()],
            [
                'type' => 'function',
                'function' => ['name' => $request->toolName()]
            ],
            [
                'type' => 'json_object',
                'schema' => $request->jsonSchema()
            ],
            $request->modelName(),
            $request->options(),
        );
    }

    protected function toChatCompletionRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeChatCompletionRequest(
            $requestClass,
            $request->prompt(),
            $request->prependInstructions($request->messages(), $request->prompt(), $request->jsonSchema(), $request->examples()),
            $request->modelName(),
            $request->options(),
        );
    }

    protected function toJsonCompletionRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeJsonCompletionRequest(
            $requestClass,
            $request->prompt(),
            $request->prependInstructions($request->messages(), $request->prompt(), $request->jsonSchema(), $request->examples()),
            [
                'type' => 'json_object',
                'schema' => $request->jsonSchema()
            ],
            $request->modelName(),
            $request->options(),
        );
    }

    protected function toToolsCallRequest(string $requestClass, Request $request) : ApiRequest {
        return $this->makeToolsCallRequest(
            $requestClass,
            $request->prompt(),
            $request->prependInstructions($request->messages(), $request->prompt(), [], $request->examples()),
            [$request->toolCallSchema()],
            [
                'type' => 'function',
                'function' => ['name' => $request->toolName()]
            ],
            $request->modelName(),
            $request->options(),
        );
    }
}

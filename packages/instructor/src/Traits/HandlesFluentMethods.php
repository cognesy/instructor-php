<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Data\StructuredOutputRequestInfo;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesFluentMethods
{
    private StructuredOutputRequestInfo $requestInfo;
    private array $cachedContext = [];

    public function withMessages(string|array $messages) : static
    {
        $this->requestInfo->messages = $messages;
        return $this;
    }

    public function withMessage(string $message) : static
    {
        $this->requestInfo->messages = [$message];
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static
    {
        $this->requestInfo->responseModel = $responseModel;
        return $this;
    }

    public function withResponseJsonSchema(array $jsonSchema) : static
    {
        $this->requestInfo->responseModel = $jsonSchema;
        return $this;
    }

    public function withResponseClass(string $class) : static
    {
        $this->requestInfo->responseModel = $class;
        return $this;
    }

    public function withResponseObject(object $responseObject) : static
    {
        $this->requestInfo->responseModel = $responseObject;
        return $this;
    }

    public function withPrompt(string $prompt) : static
    {
        $this->requestInfo->prompt = $prompt;
        return $this;
    }

    public function withInput(mixed $input) : static
    {
        $this->requestInfo->input = $input;
        return $this;
    }

    public function withModel(string $model) : static
    {
        $this->requestInfo->model = $model;
        return $this;
    }

    public function withSystem(string $system) : static
    {
        $this->requestInfo->system = $system;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static
    {
        $this->requestInfo->maxRetries = $maxRetries;
        return $this;
    }

    public function withOptions(array $options) : static
    {
        $this->requestInfo->options = $options;
        return $this;
    }

    public function withToolName(string $toolName) : static
    {
        $this->requestInfo->toolName = $toolName;
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static
    {
        $this->requestInfo->toolDescription = $toolDescription;
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt) : static
    {
        $this->requestInfo->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withMode(OutputMode $mode) : static
    {
        $this->requestInfo->mode = $mode;
        return $this;
    }

    public function withExamples(array $examples) : static
    {
        $this->requestInfo->examples = $examples;
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string|array|object $input = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->cachedContext = [
            'messages' => $messages,
            'input' => $input,
            'system' => $system,
            'prompt' => $prompt,
            'examples' => $examples,
        ];
        return $this;
    }
}
<?php
namespace Cognesy\Instructor\Data\Traits\RequestInfo;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesMutation
{
    public function withMessages(string|array $messages) : static {
        $this->messages = $messages;
        return $this;
    }

    public function withInput(string|array|object $input) : static {
        $this->input = $input;
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static {
        $this->responseModel = $responseModel;
        return $this;
    }

    public function withResponseClass(string $class) : static {
        $this->responseModel = $class;
        return $this;
    }

    public function withResponseSchema(array $jsonSchema) : static {
        $this->responseModel = $jsonSchema;
        return $this;
    }

    public function withResponseHandler(object $handler) : static {
        $this->responseModel = $handler;
        return $this;
    }

    public function withModel(string $model) : static {
        $this->model = $model;
        return $this;
    }

    public function withSystem(string $system) : static {
        $this->system = $system;
        return $this;
    }

    public function withPrompt(string $prompt) : static {
        $this->prompt = $prompt;
        return $this;
    }

    public function withExamples(array $examples) : static {
        $this->examples = $examples;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withOptions(array $options) : static {
        $this->options = $options;
        return $this;
    }

    public function withRetryPrompt($retryPrompt) : static {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withToolName($toolName) : static {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription($toolDescription) : static {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withMode(OutputMode $mode) : static {
        $this->mode = $mode;
        return $this;
    }

    public function withCachedContext(array $cachedContext) : static {
        $this->cachedContext = $cachedContext;
        return $this;
    }
}
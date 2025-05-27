<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesFluentMethods
{
    public function withMessages(string|array $messages) : static {
        $this->requestBuilder->withMessages($messages);
        return $this;
    }

    public function withMessage(string $message) : static {
        $this->requestBuilder->withMessages($message);
        return $this;
    }

    public function withInput(mixed $input) : static {
        $this->requestBuilder->withInput($input);
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static {
        $this->requestBuilder->withRequestedSchema($responseModel);
        return $this;
    }

    public function withResponseJsonSchema(array $jsonSchema) : static {
        $this->requestBuilder->withRequestedSchema($jsonSchema);
        return $this;
    }

    public function withResponseClass(string $class) : static {
        $this->requestBuilder->withRequestedSchema($class);
        return $this;
    }

    public function withResponseObject(object $responseObject) : static {
        $this->requestBuilder->withRequestedSchema($responseObject);
        return $this;
    }

    public function withPrompt(string $prompt) : static {
        $this->requestBuilder->withPrompt($prompt);
        return $this;
    }

    public function withModel(string $model) : static {
        $this->requestBuilder->withModel($model);
        return $this;
    }

    public function withSystem(string $system) : static {
        $this->requestBuilder->withSystem($system);
        return $this;
    }

    public function withOptions(array $options) : static {
        $this->requestBuilder->withOptions($options);
        return $this;
    }

    public function withStreaming(bool $streaming = true) : static {
        $this->requestBuilder->withStreaming($streaming);
        return $this;
    }

    public function withExamples(array $examples) : static {
        $this->requestBuilder->withExamples($examples);
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->requestBuilder->withCachedContext(new CachedContext($messages, $system, $prompt, $examples));
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static {
        $this->config->withMaxRetries($maxRetries);
        return $this;
    }

    public function withToolName(string $toolName) : static {
        $this->config->withToolName($toolName);
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static {
        $this->config->withToolDescription($toolDescription);
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt) : static {
        $this->config->withRetryPrompt($retryPrompt);
        return $this;
    }

    public function withOutputMode(OutputMode $mode) : static {
        $this->config->withOutputMode($mode);
        return $this;
    }
}
<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\TextRepresentation;

trait HandlesFluentMethods
{
    public function withMessages(string|array $messages) : static
    {
        $this->requestInfo->withMessages($messages);
        return $this;
    }

    public function withMessage(string $message) : static
    {
        $this->requestInfo->withMessages([['role' => 'user', 'content' => $message]]);
        return $this;
    }

    public function withInput(mixed $input) : static
    {
        $this->requestInfo->withMessages(TextRepresentation::fromAny($input));
        return $this;
    }

    public function withAnyResponseModel(string|array|object $responseModel) : static
    {
        $this->requestInfo->withResponseModel($responseModel);
        return $this;
    }

    public function withResponseJsonSchema(array $jsonSchema) : static
    {
        $this->requestInfo->withResponseModel($jsonSchema);
        return $this;
    }

    public function withResponseClass(string $class) : static
    {
        $this->requestInfo->withResponseModel($class);
        return $this;
    }

    public function withResponseObject(object $responseObject) : static
    {
        $this->requestInfo->withResponseModel($responseObject);
        return $this;
    }

    public function withPrompt(string $prompt) : static
    {
        $this->requestInfo->withPrompt($prompt);
        return $this;
    }

    public function withModel(string $model) : static
    {
        $this->requestInfo->withModel($model);
        return $this;
    }

    public function withSystem(string $system) : static
    {
        $this->requestInfo->withSystem($system);
        return $this;
    }

    public function withOptions(array $options) : static
    {
        $this->requestInfo->withOptions($options);
        return $this;
    }

    public function withStreaming(bool $streaming = true) : static
    {
        $options = $this->requestInfo->options();
        $options['stream'] = $streaming;
        $this->requestInfo->withOptions($options);
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static
    {
        $this->config->withMaxRetries($maxRetries);
        return $this;
    }

    public function withToolName(string $toolName) : static
    {
        $this->config->withToolName($toolName);
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static
    {
        $this->config->withToolDescription($toolDescription);
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt) : static
    {
        $this->config->withRetryPrompt($retryPrompt);
        return $this;
    }

    public function withOutputMode(OutputMode $mode) : static
    {
        $this->config->withOutputMode($mode);
        return $this;
    }

    public function withExamples(array $examples) : static
    {
        $this->requestInfo->withExamples($examples);
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->requestInfo->withCachedContext(new CachedContext($messages, $system, $prompt, $examples));
        return $this;
    }
}
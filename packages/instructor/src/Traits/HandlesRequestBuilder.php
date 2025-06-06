<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Core\StructuredOutputRequestBuilder;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

trait HandlesRequestBuilder
{
    private StructuredOutputRequestBuilder $requestBuilder;

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->requestBuilder->withMessages($messages);
        return $this;
    }

    public function withInput(mixed $input): static {
        $this->requestBuilder->withMessages($input);
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static {
        $this->requestBuilder->withResponseModel($responseModel);
        return $this;
    }

    public function withResponseJsonSchema(array $jsonSchema) : static {
        $this->requestBuilder->withResponseModel($jsonSchema);
        return $this;
    }

    public function withResponseClass(string $class) : static {
        $this->requestBuilder->withResponseModel($class);
        return $this;
    }

    public function withResponseObject(object $responseObject) : static {
        $this->requestBuilder->withResponseModel($responseObject);
        return $this;
    }

    public function withSystem(string $system): static {
        $this->requestBuilder->withSystem($system);
        return $this;
    }

    public function withPrompt(string $prompt): static {
        $this->requestBuilder->withPrompt($prompt);
        return $this;
    }

    public function withExamples(array $examples): static {
        $this->requestBuilder->withExamples($examples);
        return $this;
    }

    public function withModel(string $model): static {
        $this->requestBuilder->withModel($model);
        return $this;
    }

    public function withOptions(array $options): static {
        $this->requestBuilder->withOptions($options);
        return $this;
    }

    public function withOption(string $key, mixed $value): static {
        $this->requestBuilder->withOption($key, $value);
        return $this;
    }

    public function withStreaming(bool $stream = true): static {
        $this->withOption('stream', $stream);
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->requestBuilder->withCachedContext($messages, $system, $prompt, $examples);
        return $this;
    }
}

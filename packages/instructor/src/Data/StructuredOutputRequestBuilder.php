<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\TextRepresentation;
use Exception;

class StructuredOutputRequestBuilder
{
    private Messages $messages;
    private string $system = '';
    private string $prompt = '';
    private array $examples = [];
    private string $model = '';
    private array $options = [];
    private CachedContext $cachedContext;
    private string|array|object $requestedSchema = [];
    private StructuredOutputConfig $config;

    private ?ResponseModel $responseModel = null;

    public function __construct(
        ?StructuredOutputConfig $config = null,
    ) {
        $this->messages = new Messages();
        $this->cachedContext = new CachedContext();
        $this->config = $config ?? StructuredOutputConfig::default();
    }

    public function build() : StructuredOutputRequest {
        if (empty($this->requestedSchema) && empty($this->responseModel)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        return new StructuredOutputRequest(
            messages: $this->messages ?? null,
            requestedSchema: $this->requestedSchema ?? [],
            responseModel: $this->responseModel ?? null,
            system: $this->system ?: null,
            prompt: $this->prompt ?: null,
            examples: $this->examples ?: null,
            model: $this->model ?? null,
            options: $this->options ?? null,
            cachedContext: $this->cachedContext,
            config: $this->config,
        );
    }

    public function requestedSchema() : string|array|object {
        return $this->requestedSchema;
    }

    public function withMessages(string|array|Message|Messages $messages) : static {
        $this->messages = Messages::fromAny($messages);
        return $this;
    }

    public function withInput(mixed $input) : static {
        $this->messages = Messages::fromAny(TextRepresentation::fromAny($input));
        return $this;
    }

    public function withRequestedSchema(string|array|object $requestedSchema) : static {
        $this->requestedSchema = $requestedSchema;
        return $this;
    }

    public function withResponseModel(ResponseModel $responseModel) : static {
        $this->responseModel = $responseModel;
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

    public function withModel(string $model) : static {
        $this->model = $model;
        return $this;
    }

    public function withOptions(array $options) : static {
        $this->options = $options;
        return $this;
    }

    public function withOption(string $key, mixed $value) : static {
        if ($this->options === null) {
            $this->options = [];
        }
        $this->options[$key] = $value;
        return $this;
    }

    public function withOutputMode(OutputMode $outputMode) : static {
        $this->config->withOutputMode($outputMode);
        return $this;
    }

    public function withCachedContext(CachedContext $cachedContext) : static {
        $this->cachedContext = $cachedContext;
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config) : static {
        $this->config = $config;
        return $this;
    }

    public function withStreaming(bool $stream = true) : static {
        $this->withOption('stream', $stream);
        return $this;
    }

    public function withDefaults() : static {
        $this->messages = new Messages();
        $this->requestedSchema = [];
        $this->system = '';
        $this->prompt = '';
        $this->examples = [];
        $this->model = '';
        $this->options = [];
        $this->cachedContext = new CachedContext();
        $this->responseModel = null;
        $this->config = StructuredOutputConfig::default();

        return $this;
    }

    public function with(
        string|array|Message|Messages $messages = '',
        string|array|object $responseModel = [],
        string              $system = '',
        string              $prompt = '',
        array               $examples = [],
        string              $model = '',
        int                 $maxRetries = -1,
        array               $options = [],
        string              $toolName = '',
        string              $toolDescription = '',
        string              $retryPrompt = '',
        ?OutputMode         $mode = null,
    ) : static {
        $this->messages = match(true) {
            empty($messages) => $this->messages,
            default => Messages::fromAny($messages),
        };
        $this->requestedSchema = $responseModel ?: $this->requestedSchema;
        $this->system = $system ?: $this->system;
        $this->prompt = $prompt ?: $this->prompt;
        $this->examples = $examples ?: $this->examples;
        $this->model = $model ?: $this->model;
        $this->options = array_merge($this->options, $options);
        $this->cachedContext = new CachedContext();
        $this->requestedSchema = $responseModel ?: $this->requestedSchema;

        $this->config->withOverrides(
            outputMode: $mode ?: $this->config->outputMode(),
            maxRetries: ($maxRetries >= 0) ? $maxRetries : $this->config->maxRetries(),
            retryPrompt: $retryPrompt ?: $this->config->retryPrompt(),
            toolName: $toolName ?: $this->config->toolName(),
            toolDescription: $toolDescription ?: $this->config->toolDescription(),
        );

        return $this;
    }
}
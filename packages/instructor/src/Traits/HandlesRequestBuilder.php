<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Utils\ReferenceQueue;
use Cognesy\Utils\Events\Contracts\EventListenerInterface;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\TextRepresentation;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

trait HandlesRequestBuilder
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

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->messages = Messages::fromAny($messages);
        return $this;
    }

    public function withInput(mixed $input): static {
        $this->messages = Messages::fromAny(TextRepresentation::fromAny($input));
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static {
        $this->requestedSchema = $responseModel;
        return $this;
    }

    public function withResponseJsonSchema(array $jsonSchema) : static {
        $this->requestedSchema = $jsonSchema;
        return $this;
    }

    public function withResponseClass(string $class) : static {
        $this->requestedSchema = $class;
        return $this;
    }

    public function withResponseObject(object $responseObject) : static {
        $this->requestedSchema = $responseObject;
        return $this;
    }

    public function withSystem(string $system): static {
        $this->system = $system;
        return $this;
    }

    public function withPrompt(string $prompt): static {
        $this->prompt = $prompt;
        return $this;
    }

    public function withExamples(array $examples): static {
        $this->examples = $examples;
        return $this;
    }

    public function withModel(string $model): static {
        $this->model = $model;
        return $this;
    }

    public function withOptions(array $options): static {
        $this->options = $options;
        return $this;
    }

    public function withOption(string $key, mixed $value): static {
        if ($this->options === null) {
            $this->options = [];
        }
        $this->options[$key] = $value;
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
        $this->cachedContext = new CachedContext($messages, $system, $prompt, $examples);
        return $this;
    }

    public function withOutputMode(OutputMode $outputMode): static {
        $this->config->withOutputMode($outputMode);
        return $this;
    }

    public function withMaxRetries(int $maxRetries): static {
        $this->config->withMaxRetries($maxRetries);
        return $this;
    }

    public function withSchemaName(string $schemaName): static {
        $this->config->withSchemaName($schemaName);
        return $this;
    }

    public function withToolName(string $toolName): static {
        $this->config->withToolName($toolName);
        return $this;
    }

    public function withToolDescription(string $toolDescription): static {
        $this->config->withToolDescription($toolDescription);
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt): static {
        $this->config->withRetryPrompt($retryPrompt);
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config): static {
        $this->config = $config;
        return $this;
    }

    public function withObjectReferences(bool $useObjectReferences): static {
        $this->config->withUseObjectReferences($useObjectReferences);
        return $this;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    protected function build(): StructuredOutputRequest {
        if (empty($this->requestedSchema)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        return new StructuredOutputRequest(
            messages: $this->messages,
            requestedSchema: $this->requestedSchema ?? [],
            responseModel: $this->makeResponseModel(
                $this->requestedSchema,
                $this->config,
                $this->events,
                $this->listener,
            ),
            system: $this->system ?: null,
            prompt: $this->prompt ?: null,
            examples: $this->examples ?: null,
            model: $this->model ?? null,
            options: $this->options ?? null,
            cachedContext: $this->cachedContext,
            config: $this->config,
        );
    }

    private function makeResponseModel(
        string|array|object $requestedSchema,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
        EventListenerInterface $listener,
    ): ResponseModel {
        $schemaFactory = new SchemaFactory(
            $config->useObjectReferences(),
            new JsonSchemaToSchema(
                defaultToolName: $config->toolName(),
                defaultToolDescription: $config->toolDescription(),
                defaultOutputClass: $config->defaultOutputClass(),
            )
        );
        $toolCallBuilder = new ToolCallBuilder(
            $schemaFactory,
            new ReferenceQueue()
        );
        $responseModelFactory = new ResponseModelFactory(
            toolCallBuilder: $toolCallBuilder,
            schemaFactory: $schemaFactory,
            config: $config,
            events: $events,
            listener: $listener,
        );
        return $responseModelFactory->fromAny(
            $requestedSchema,
            $config->toolName(),
            $config->toolDescription()
        );
    }
}

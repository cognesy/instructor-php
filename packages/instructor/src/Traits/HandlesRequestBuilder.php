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
        $this->withCachedContext($messages, $system, $prompt, $examples);
        return $this;
    }


//    private Messages $messages;
//    private string $system = '';
//    private string $prompt = '';
//    private array $examples = [];
//    private string $model = '';
//    private array $options = [];
//    private CachedContext $cachedContext;
//    private string|array|object $requestedSchema = [];
//
//    public function withMessages(string|array|Message|Messages $messages): static {
//        $this->messages = Messages::fromAny($messages);
//        return $this;
//    }
//
//    public function withInput(mixed $input): static {
//        $this->messages = Messages::fromAny(TextRepresentation::fromAny($input));
//        return $this;
//    }
//
//    public function withResponseModel(string|array|object $responseModel) : static {
//        $this->requestedSchema = $responseModel;
//        return $this;
//    }
//
//    public function withResponseJsonSchema(array $jsonSchema) : static {
//        $this->requestedSchema = $jsonSchema;
//        return $this;
//    }
//
//    public function withResponseClass(string $class) : static {
//        $this->requestedSchema = $class;
//        return $this;
//    }
//
//    public function withResponseObject(object $responseObject) : static {
//        $this->requestedSchema = $responseObject;
//        return $this;
//    }
//
//    public function withSystem(string $system): static {
//        $this->system = $system;
//        return $this;
//    }
//
//    public function withPrompt(string $prompt): static {
//        $this->prompt = $prompt;
//        return $this;
//    }
//
//    public function withExamples(array $examples): static {
//        $this->examples = $examples;
//        return $this;
//    }
//
//    public function withModel(string $model): static {
//        $this->model = $model;
//        return $this;
//    }
//
//    public function withOptions(array $options): static {
//        $this->options = $options;
//        return $this;
//    }
//
//    public function withOption(string $key, mixed $value): static {
//        if ($this->options === null) {
//            $this->options = [];
//        }
//        $this->options[$key] = $value;
//        return $this;
//    }
//
//    public function withStreaming(bool $stream = true): static {
//        $this->withOption('stream', $stream);
//        return $this;
//    }
//
//    public function withCachedContext(
//        string|array $messages = '',
//        string $system = '',
//        string $prompt = '',
//        array $examples = [],
//    ) : ?self {
//        $this->cachedContext = new CachedContext($messages, $system, $prompt, $examples);
//        return $this;
//    }
//
//    // INTERNAL /////////////////////////////////////////////////////////////////
//
//    protected function build(): StructuredOutputRequest {
//        if (empty($this->requestedSchema)) {
//            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
//        }
//
//        return new StructuredOutputRequest(
//            messages: $this->messages,
//            requestedSchema: $this->requestedSchema ?? [],
//            responseModel: $this->makeResponseModel(
//                $this->requestedSchema,
//                $this->config,
//                $this->events,
//            ),
//            system: $this->system ?: null,
//            prompt: $this->prompt ?: null,
//            examples: $this->examples ?: null,
//            model: $this->model ?? null,
//            options: $this->options ?? null,
//            cachedContext: $this->cachedContext,
//            config: $this->config,
//        );
//    }
//
//    private function makeResponseModel(
//        string|array|object $requestedSchema,
//        StructuredOutputConfig $config,
//        EventDispatcherInterface $events,
//    ): ResponseModel {
//        $schemaFactory = new SchemaFactory(
//            $config->useObjectReferences(),
//            new JsonSchemaToSchema(
//                defaultToolName: $config->toolName(),
//                defaultToolDescription: $config->toolDescription(),
//                defaultOutputClass: $config->defaultOutputClass(),
//            )
//        );
//        $toolCallBuilder = new ToolCallBuilder(
//            $schemaFactory,
//            new ReferenceQueue()
//        );
//        $responseModelFactory = new ResponseModelFactory(
//            toolCallBuilder: $toolCallBuilder,
//            schemaFactory: $schemaFactory,
//            config: $config,
//            events: $events,
//        );
//        return $responseModelFactory->fromAny(
//            $requestedSchema,
//            $config->toolName(),
//            $config->toolDescription()
//        );
//    }
}

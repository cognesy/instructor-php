<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class StructuredOutputRequestBuilder
{
    private Messages $messages;
    private string $system = '';
    private string $prompt = '';
    /** @var Example[] */
    private array $examples = [];
    private string $model = '';
    private array $options = [];
    private CachedContext $cachedContext;
    private string|array|object $requestedSchema = [];
    private ?ResponseModel $responseModel = null;

    public function __construct()
    {
        $this->messages = Messages::empty();
        $this->cachedContext = new CachedContext();
    }

    /**
     * Sets all parameters for the structured output request and returns the current instance.
     */
    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $requestedSchema = null,
        ?string $system = null,
        ?string $prompt = null,
        ?array $examples = null,
        ?string $model = null,
        ?array $options = null,
        ?CachedContext $cachedContext = null,
    ): static {
        $this->messages = $messages !== null ? Messages::fromAny($messages) : $this->messages;
        $this->requestedSchema = $requestedSchema ?? $this->requestedSchema;
        $this->system = $system ?? $this->system;
        $this->prompt = $prompt ?? $this->prompt;
        $this->examples = $examples ?? $this->examples;
        $this->model = $model ?? $this->model;
        $this->options = $options !== null ? array_merge($this->options, $options) : $this->options;
        $this->cachedContext = $cachedContext ?? $this->cachedContext;
        return $this;
    }

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->messages = Messages::fromAny($messages);
        return $this;
    }

    public function withInput(mixed $input): static {
        $this->messages = Messages::fromAny($input);
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel): static {
        $this->requestedSchema = $responseModel;
        return $this;
    }

    public function withResponseJsonSchema(array $jsonSchema): static {
        $this->requestedSchema = $jsonSchema;
        return $this;
    }

    public function withResponseClass(string $class): static {
        $this->requestedSchema = $class;
        return $this;
    }

    public function withResponseObject(object $responseObject): static {
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
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function withOption(string $key, mixed $value): static {
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
    ): static {
        $this->cachedContext = new CachedContext($messages, $system, $prompt, $examples);
        return $this;
    }

    public function withRequest(StructuredOutputRequest $request): static {
        $this->messages = $request->messages();
        $this->requestedSchema = $request->requestedSchema();
        $this->responseModel = $request->responseModel();
        $this->system = $request->system();
        $this->prompt = $request->prompt();
        $this->examples = $request->examples();
        $this->model = $request->model();
        $this->options = array_merge($this->options, $request->options());
        $this->cachedContext = $request->cachedContext();
        return $this;
    }

    public function createWith(
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
    ) : StructuredOutputRequest {
        if (empty($this->requestedSchema)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        return new StructuredOutputRequest(
            messages: $this->messages,
            requestedSchema: $this->requestedSchema ?? [],
            responseModel: $this->responseModel ?? $this->makeResponseModel(
                $this->requestedSchema,
                $config,
                $events,
            ),
            system: $this->system ?: null,
            prompt: $this->prompt ?: null,
            examples: $this->examples ?: null,
            model: $this->model ?? null,
            options: $this->options ?? null,
            cachedContext: $this->cachedContext,
            config: $config,
        );
    }

    private function makeResponseModel(
        string|array|object $requestedSchema,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
    ): ResponseModel {
        $schemaFactory = new SchemaFactory(
            useObjectReferences: $config->useObjectReferences(),
            schemaConverter: new JsonSchemaToSchema(
                defaultToolName: $config->toolName(),
                defaultToolDescription: $config->toolDescription(),
                defaultOutputClass: $config->outputClass(),
            )
        );
        $toolCallBuilder = new ToolCallBuilder($schemaFactory);
        $responseModelFactory = new ResponseModelFactory(
            toolCallBuilder: $toolCallBuilder,
            schemaFactory: $schemaFactory,
            config: $config,
            events: $events,
        );
        return $responseModelFactory->fromAny($requestedSchema);
    }
}
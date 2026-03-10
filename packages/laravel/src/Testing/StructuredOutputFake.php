<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Fake implementation of StructuredOutput for testing.
 *
 * Allows you to mock LLM responses and make assertions about
 * how your code interacts with StructuredOutput.
 *
 * @example
 * ```php
 * // In your test
 * $fake = StructuredOutput::fake([
 *     PersonData::class => new PersonData(name: 'John', age: 30),
 * ]);
 *
 * // Your code calls StructuredOutput
 * $person = StructuredOutput::with(
 *     messages: 'Extract person data from: John is 30 years old',
 *     responseModel: PersonData::class,
 * )->get();
 *
 * // Make assertions
 * $fake->assertExtracted(PersonData::class);
 * $fake->assertExtractedTimes(PersonData::class, 1);
 * ```
 */
class StructuredOutputFake
{
    /** @var array<class-string, mixed> */
    protected array $responses = [];

    /** @var array<int, array{class: string, messages: ?Messages, model: ?string, connection: ?string, llmConfig: ?array}> */
    protected array $recorded = [];

    protected ?string $connection = null;

    /** @var array<string,mixed>|null */
    protected ?array $llmConfig = null;

    protected ?Messages $messages = null;

    protected string|array|object|null $responseModel = null;

    protected ?string $system = null;

    protected ?string $prompt = null;

    protected ?array $examples = null;

    protected ?string $model = null;

    protected array $options = [];

    /**
     * Create a new fake instance.
     *
     * @param array<class-string, mixed> $responses Map of response class to fake response
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    /**
     * Queue a response for a specific response model class.
     */
    public function respondWith(string $class, mixed $response): self
    {
        $this->responses[$class] = $response;
        return $this;
    }

    /**
     * Queue multiple responses for a class (will be used in order).
     */
    public function respondWithSequence(string $class, array $responses): self
    {
        $this->responses[$class] = $responses;
        return $this;
    }

    // Fluent API methods to match StructuredOutput

    public function connection(string $name): self
    {
        $this->connection = $name;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config): self
    {
        $this->llmConfig = $config->toArray();
        return $this;
    }

    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $responseModel = null,
        ?string $system = null,
        ?string $prompt = null,
        ?array $examples = null,
        ?string $model = null,
        ?array $options = null,
    ): self {
        $this->messages = $messages !== null ? Messages::fromAny($messages) : $this->messages;
        $this->responseModel = $responseModel ?? $this->responseModel;
        $this->system = $system ?? $this->system;
        $this->prompt = $prompt ?? $this->prompt;
        $this->examples = $examples ?? $this->examples;
        $this->model = $model ?? $this->model;
        $this->options = $options ?? $this->options;
        return $this;
    }

    public function withMessages(string|array|Message|Messages $messages): self
    {
        $this->messages = Messages::fromAny($messages);
        return $this;
    }

    public function withInput(string|array|object $input): self
    {
        $this->messages = Messages::fromInput($input);
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel): self
    {
        $this->responseModel = $responseModel;
        return $this;
    }

    public function withResponseJsonSchema(array|CanProvideJsonSchema $jsonSchema): self
    {
        $this->responseModel = $jsonSchema;
        return $this;
    }

    public function withResponseClass(string $class): self
    {
        $this->responseModel = $class;
        return $this;
    }

    public function withResponseObject(object $object): self
    {
        $this->responseModel = $object;
        return $this;
    }

    public function withSystem(string $system): self
    {
        $this->system = $system;
        return $this;
    }

    public function withPrompt(string $prompt): self
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function withExamples(array $examples): self
    {
        $this->examples = $examples;
        return $this;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function withOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function withStreaming(bool $streaming = true): self
    {
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ): self {
        return $this;
    }

    public function intoArray(): self
    {
        return $this;
    }

    public function intoInstanceOf(string $class): self
    {
        return $this;
    }

    public function intoObject(CanDeserializeSelf $object): self
    {
        return $this;
    }

    public function withRuntime($runtime): self
    {
        return $this;
    }

    public function withValidators(...$validators): self
    {
        return $this;
    }

    public function withTransformers(...$transformers): self
    {
        return $this;
    }

    public function withDeserializers(...$deserializers): self
    {
        return $this;
    }

    public function withExtractors(...$extractors): self
    {
        return $this;
    }

    public function wiretap(callable $callback): self
    {
        return $this;
    }

    public function create(): self
    {
        return $this;
    }

    /**
     * Execute and return the fake response.
     */
    public function get(): mixed
    {
        return $this->resolveResponse();
    }

    public function getString(): string
    {
        return (string) $this->get();
    }

    public function getInt(): int
    {
        return (int) $this->get();
    }

    public function getFloat(): float
    {
        return (float) $this->get();
    }

    public function getBoolean(): bool
    {
        return (bool) $this->get();
    }

    public function getObject(): object
    {
        $result = $this->get();
        return is_object($result) ? $result : (object) $result;
    }

    public function getArray(): array
    {
        return (array) $this->get();
    }

    public function response(): object
    {
        return (object) [
            'value' => $this->get(),
        ];
    }

    public function inferenceResponse(): object
    {
        return (object) [
            'content' => '',
            'toolCalls' => [],
            'finishReason' => 'stop',
        ];
    }

    public function stream(): iterable
    {
        yield $this->get();
    }

    /**
     * Resolve the fake response.
     */
    protected function resolveResponse(): mixed
    {
        $class = $this->getResponseModelClass();

        // Record the extraction
        $this->recorded[] = [
            'class' => $class,
            'messages' => $this->messages,
            'model' => $this->model,
            'connection' => $this->connection,
            'llmConfig' => $this->llmConfig,
        ];

        // Reset state
        $this->messages = null;
        $this->model = null;
        $this->connection = null;
        $this->llmConfig = null;
        $this->responseModel = null;
        $this->system = null;
        $this->prompt = null;
        $this->examples = null;

        // Find matching response
        if (isset($this->responses[$class])) {
            $response = $this->responses[$class];

            // Handle sequence of responses
            if (is_array($response) && !empty($response) && array_is_list($response)) {
                return array_shift($this->responses[$class]);
            }

            return $response;
        }

        // No fake response defined - throw helpful error
        throw new \RuntimeException(
            "No fake response defined for [{$class}]. " .
            "Use StructuredOutput::fake(['{$class}' => \$response]) to define one."
        );
    }

    /**
     * Get the response model class name.
     */
    protected function getResponseModelClass(): string
    {
        if (is_string($this->responseModel)) {
            return $this->responseModel;
        }

        if (is_object($this->responseModel)) {
            return get_class($this->responseModel);
        }

        return 'unknown';
    }

    // Assertions

    /**
     * Assert that extraction was called for a specific response model.
     */
    public function assertExtracted(string $class): self
    {
        $found = collect($this->recorded)->contains('class', $class);

        PHPUnit::assertTrue(
            $found,
            "Expected extraction for [{$class}] was not performed."
        );

        return $this;
    }

    /**
     * Assert extraction was called a specific number of times.
     */
    public function assertExtractedTimes(string $class, int $times): self
    {
        $count = collect($this->recorded)->where('class', $class)->count();

        PHPUnit::assertEquals(
            $times,
            $count,
            "Expected [{$class}] to be extracted {$times} time(s), but was extracted {$count} time(s)."
        );

        return $this;
    }

    /**
     * Assert no extractions were performed.
     */
    public function assertNothingExtracted(): self
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Expected no extractions, but ' . count($this->recorded) . ' were performed.'
        );

        return $this;
    }

    /**
     * Assert extraction was called with specific messages.
     */
    public function assertExtractedWith(string $class, string $messages): self
    {
        $found = collect($this->recorded)
            ->where('class', $class)
            ->contains(function ($record) use ($messages) {
                $recorded = $record['messages'];
                $haystack = match (true) {
                    $recorded instanceof Messages => $recorded->toString(),
                    is_string($recorded) => $recorded,
                    default => json_encode($recorded) ?: '',
                };
                return str_contains($haystack, $messages);
            });

        PHPUnit::assertTrue(
            $found,
            "Expected extraction for [{$class}] with specified messages was not found."
        );

        return $this;
    }

    /**
     * Assert extraction used a specific configured connection.
     */
    public function assertUsedConnection(string $connection): self
    {
        $found = collect($this->recorded)->contains('connection', $connection);

        PHPUnit::assertTrue(
            $found,
            "Expected connection [{$connection}] was not used."
        );

        return $this;
    }

    /**
     * Assert extraction used a specific model.
     */
    public function assertUsedModel(string $model): self
    {
        $found = collect($this->recorded)->contains('model', $model);

        PHPUnit::assertTrue(
            $found,
            "Expected model [{$model}] was not used."
        );

        return $this;
    }

    /**
     * Get all recorded extractions.
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}

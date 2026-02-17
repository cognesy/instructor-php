<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
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

    /** @var array<int, array{class: string, messages: mixed, model: ?string}> */
    protected array $recorded = [];

    /** @var string|null */
    protected ?string $preset = null;

    /** @var string|array|null */
    protected string|array|null $messages = null;

    /** @var string|array|object|null */
    protected string|array|object|null $responseModel = null;

    /** @var string|null */
    protected ?string $model = null;

    /** @var array */
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

    public function using(string $preset): self
    {
        $this->preset = $preset;
        return $this;
    }

    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $responseModel = null,
        ?string $system = null,
        ?string $prompt = null,
        ?array $examples = null,
        ?string $model = null,
        ?int $maxRetries = null,
        ?array $options = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?string $retryPrompt = null,
        ?OutputMode $mode = null,
    ): self {
        $this->messages = $messages;
        $this->responseModel = $responseModel;
        $this->model = $model;
        $this->options = $options ?? [];
        return $this;
    }

    public function withMessages(string|array|Message|Messages $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel): self
    {
        $this->responseModel = $responseModel;
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

    public function withMaxRetries(int $maxRetries): self
    {
        return $this;
    }

    public function withOutputMode(OutputMode $mode): self
    {
        return $this;
    }

    public function withStreaming(bool $streaming = true): self
    {
        return $this;
    }

    public function withHttpClient($httpClient): self
    {
        return $this;
    }

    public function withHttpDebugPreset(?string $preset): self
    {
        return $this;
    }

    public function withHttpDebug(bool $enabled = true): self
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

    public function onPartialUpdate(callable $callback): self
    {
        return $this;
    }

    public function onSequenceUpdate(callable $callback): self
    {
        return $this;
    }

    public function wiretap(callable $callback): self
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
            'preset' => $this->preset,
        ];

        // Reset state
        $messages = $this->messages;
        $model = $this->model;
        $preset = $this->preset;
        $this->messages = null;
        $this->model = null;
        $this->preset = null;
        $this->responseModel = null;

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
    public function assertExtractedWith(string $class, string|array $messages): self
    {
        $found = collect($this->recorded)
            ->where('class', $class)
            ->contains(function ($record) use ($messages) {
                if (is_string($messages)) {
                    return str_contains(json_encode($record['messages']), $messages);
                }
                return $record['messages'] === $messages;
            });

        PHPUnit::assertTrue(
            $found,
            "Expected extraction for [{$class}] with specified messages was not found."
        );

        return $this;
    }

    /**
     * Assert extraction used a specific preset.
     */
    public function assertUsedPreset(string $preset): self
    {
        $found = collect($this->recorded)->contains('preset', $preset);

        PHPUnit::assertTrue(
            $found,
            "Expected preset [{$preset}] was not used."
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

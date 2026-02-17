<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Fake implementation of Embeddings for testing.
 *
 * Allows you to mock embedding responses and make assertions about
 * how your code interacts with the Embeddings facade.
 *
 * @example
 * ```php
 * // In your test
 * $fake = Embeddings::fake([
 *     'Hello world' => [0.1, 0.2, 0.3, ...],
 * ]);
 *
 * // Your code calls Embeddings
 * $embedding = Embeddings::withInputs('Hello world')->first();
 *
 * // Make assertions
 * $fake->assertCalled();
 * $fake->assertCalledWith('Hello world');
 * ```
 */
class EmbeddingsFake
{
    /** @var array<string, array<float>> */
    protected array $responses = [];

    /** @var array<int, array{inputs: mixed, model: ?string, preset: ?string, options: array}> */
    protected array $recorded = [];

    /** @var string|null */
    protected ?string $preset = null;

    /** @var string|array|null */
    protected string|array|null $inputs = null;

    /** @var string|null */
    protected ?string $model = null;

    /** @var array */
    protected array $options = [];

    /** @var array<float> Default embedding vector for testing */
    protected array $defaultEmbedding;

    /**
     * Create a new fake instance.
     *
     * @param array<string, array<float>> $responses Map of input patterns to embedding vectors
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
        // Generate a default random embedding (1536 dimensions like OpenAI)
        $this->defaultEmbedding = $this->generateRandomEmbedding(1536);
    }

    /**
     * Generate a random normalized embedding vector.
     */
    protected function generateRandomEmbedding(int $dimensions): array
    {
        $embedding = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $embedding[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }

        // Normalize the vector
        $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        return array_map(fn($x) => $x / $magnitude, $embedding);
    }

    /**
     * Queue a response for a specific input pattern.
     */
    public function respondWith(string $pattern, array $embedding): self
    {
        $this->responses[$pattern] = $embedding;
        return $this;
    }

    /**
     * Set the default embedding dimensions.
     */
    public function withDimensions(int $dimensions): self
    {
        $this->defaultEmbedding = $this->generateRandomEmbedding($dimensions);
        return $this;
    }

    // Fluent API methods to match Embeddings

    public function using(string $preset): self
    {
        $this->preset = $preset;
        return $this;
    }

    public function withInputs(string|array $inputs): self
    {
        $this->inputs = $inputs;
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

    public function withHttpClient($httpClient): self
    {
        return $this;
    }

    public function withConfig($config): self
    {
        return $this;
    }

    public function withConfigOverrides(array $overrides): self
    {
        return $this;
    }

    public function withDsn(string $dsn): self
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

    public function wiretap(callable $callback): self
    {
        return $this;
    }

    public function onEvent(string $eventClass, callable $callback): self
    {
        return $this;
    }

    public function create(): self
    {
        return $this;
    }

    /**
     * Get the embedding response.
     */
    public function get(): object
    {
        $embeddings = $this->resolveResponse();

        return (object) [
            'embeddings' => $embeddings,
            'usage' => (object) [
                'promptTokens' => 10,
                'totalTokens' => 10,
            ],
        ];
    }

    /**
     * Get the first embedding vector.
     */
    public function first(): array
    {
        $embeddings = $this->resolveResponse();
        return $embeddings[0] ?? $this->defaultEmbedding;
    }

    /**
     * Get all embedding vectors.
     */
    public function all(): array
    {
        return $this->resolveResponse();
    }

    /**
     * Resolve the fake response.
     */
    protected function resolveResponse(): array
    {
        // Record the call
        $this->recorded[] = [
            'inputs' => $this->inputs,
            'model' => $this->model,
            'preset' => $this->preset,
            'options' => $this->options,
        ];

        // Get inputs as array
        $inputs = is_array($this->inputs) ? $this->inputs : [$this->inputs];

        // Reset state
        $savedInputs = $this->inputs;
        $this->inputs = null;
        $this->model = null;
        $this->preset = null;
        $this->options = [];

        // Resolve embeddings for each input
        $embeddings = [];
        foreach ($inputs as $input) {
            $embeddings[] = $this->resolveEmbeddingForInput($input);
        }

        return $embeddings;
    }

    /**
     * Resolve embedding for a single input.
     */
    protected function resolveEmbeddingForInput(?string $input): array
    {
        if ($input === null) {
            return $this->defaultEmbedding;
        }

        // Check for exact match
        if (isset($this->responses[$input])) {
            return $this->responses[$input];
        }

        // Check for pattern match
        foreach ($this->responses as $pattern => $embedding) {
            if (str_contains($input, $pattern)) {
                return $embedding;
            }
        }

        // Return default embedding
        return $this->defaultEmbedding;
    }

    // Assertions

    /**
     * Assert that embeddings were called at least once.
     */
    public function assertCalled(): self
    {
        PHPUnit::assertNotEmpty(
            $this->recorded,
            'Expected embeddings to be called, but they were not.'
        );

        return $this;
    }

    /**
     * Assert embeddings were called a specific number of times.
     */
    public function assertCalledTimes(int $times): self
    {
        $count = count($this->recorded);

        PHPUnit::assertEquals(
            $times,
            $count,
            "Expected embeddings to be called {$times} time(s), but was called {$count} time(s)."
        );

        return $this;
    }

    /**
     * Assert embeddings were never called.
     */
    public function assertNotCalled(): self
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Expected embeddings not to be called, but they were called ' . count($this->recorded) . ' time(s).'
        );

        return $this;
    }

    /**
     * Assert embeddings were called with specific input.
     */
    public function assertCalledWith(string|array $inputs): self
    {
        $inputsToCheck = is_array($inputs) ? $inputs : [$inputs];

        $found = collect($this->recorded)
            ->contains(function ($record) use ($inputsToCheck) {
                $recordedInputs = is_array($record['inputs']) ? $record['inputs'] : [$record['inputs']];
                return empty(array_diff($inputsToCheck, $recordedInputs));
            });

        PHPUnit::assertTrue(
            $found,
            'Expected embeddings to be called with specified inputs, but they were not.'
        );

        return $this;
    }

    /**
     * Assert embeddings used a specific preset.
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
     * Assert embeddings used a specific model.
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
     * Get all recorded calls.
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}

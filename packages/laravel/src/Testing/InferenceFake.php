<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Fake implementation of Inference for testing.
 *
 * Allows you to mock LLM responses and make assertions about
 * how your code interacts with the Inference facade.
 *
 * @example
 * ```php
 * // In your test
 * $fake = Inference::fake([
 *     'What is 2+2?' => 'The answer is 4.',
 *     'default' => 'I don\'t know.',
 * ]);
 *
 * // Your code calls Inference
 * $response = Inference::with(
 *     messages: 'What is 2+2?',
 * )->get();
 *
 * // Make assertions
 * $fake->assertCalled();
 * $fake->assertCalledTimes(1);
 * ```
 */
class InferenceFake
{
    /** @var array<string, string|array> */
    protected array $responses = [];

    /** @var array<int, array{messages: mixed, model: ?string, preset: ?string, tools: array, options: array}> */
    protected array $recorded = [];

    /** @var string|null */
    protected ?string $preset = null;

    /** @var string|array|null */
    protected string|array|null $messages = null;

    /** @var string|null */
    protected ?string $model = null;

    /** @var array */
    protected array $tools = [];

    /** @var string|array */
    protected string|array $toolChoice = [];

    /** @var array */
    protected array $responseFormat = [];

    /** @var array */
    protected array $options = [];

    /** @var OutputMode|null */
    protected ?OutputMode $mode = null;

    /** @var bool */
    protected bool $streaming = false;

    /**
     * Create a new fake instance.
     *
     * @param array<string, string|array> $responses Map of message patterns to responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    /**
     * Queue a response for a specific message pattern.
     */
    public function respondWith(string $pattern, string|array $response): self
    {
        $this->responses[$pattern] = $response;
        return $this;
    }

    /**
     * Queue multiple responses (will be used in order).
     */
    public function respondWithSequence(array $responses): self
    {
        $this->responses['_sequence'] = $responses;
        return $this;
    }

    // Fluent API methods to match Inference

    public function using(string $preset): self
    {
        $this->preset = $preset;
        return $this;
    }

    public function with(
        string|array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
        array $options = [],
        ?OutputMode $mode = null,
    ): self {
        $this->messages = $messages;
        $this->model = $model ?: null;
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;
        $this->options = $options;
        $this->mode = $mode;
        return $this;
    }

    public function withMessages(string|array|Message|Messages $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function withTools(array $tools): self
    {
        $this->tools = $tools;
        return $this;
    }

    public function withToolChoice(string|array $toolChoice): self
    {
        $this->toolChoice = $toolChoice;
        return $this;
    }

    public function withResponseFormat(array $responseFormat): self
    {
        $this->responseFormat = $responseFormat;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function withOutputMode(OutputMode $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function withStreaming(bool $streaming = true): self
    {
        $this->streaming = $streaming;
        return $this;
    }

    public function withHttpClient($httpClient): self
    {
        return $this;
    }

    public function withLLMConfig(LLMConfig $config): self
    {
        return $this;
    }

    public function withLLMConfigOverrides(array $overrides): self
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
     * Execute and return the fake response.
     */
    public function get(): string
    {
        return $this->resolveResponse();
    }

    public function asJson(): string
    {
        return $this->get();
    }

    public function asJsonData(): array
    {
        $response = $this->get();
        return json_decode($response, true) ?? [];
    }

    public function response(): object
    {
        return (object) [
            'content' => $this->get(),
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
    protected function resolveResponse(): string
    {
        // Record the call
        $this->recorded[] = [
            'messages' => $this->messages,
            'model' => $this->model,
            'preset' => $this->preset,
            'tools' => $this->tools,
            'options' => $this->options,
        ];

        // Get message string for matching
        $messageString = $this->getMessageString();

        // Reset state
        $this->messages = null;
        $this->model = null;
        $this->preset = null;
        $this->tools = [];
        $this->options = [];

        // Handle sequence responses
        if (isset($this->responses['_sequence']) && is_array($this->responses['_sequence'])) {
            if (!empty($this->responses['_sequence'])) {
                return array_shift($this->responses['_sequence']);
            }
        }

        // Find matching response by pattern
        foreach ($this->responses as $pattern => $response) {
            if ($pattern === '_sequence') {
                continue;
            }

            if ($pattern === 'default') {
                continue;
            }

            if (str_contains($messageString, $pattern)) {
                if (is_array($response) && array_is_list($response)) {
                    return array_shift($this->responses[$pattern]);
                }
                return is_array($response) ? json_encode($response) : $response;
            }
        }

        // Return default response
        if (isset($this->responses['default'])) {
            return $this->responses['default'];
        }

        return '';
    }

    /**
     * Get message as string for matching.
     */
    protected function getMessageString(): string
    {
        if (is_string($this->messages)) {
            return $this->messages;
        }

        return json_encode($this->messages) ?: '';
    }

    // Assertions

    /**
     * Assert that inference was called at least once.
     */
    public function assertCalled(): self
    {
        PHPUnit::assertNotEmpty(
            $this->recorded,
            'Expected inference to be called, but it was not.'
        );

        return $this;
    }

    /**
     * Assert inference was called a specific number of times.
     */
    public function assertCalledTimes(int $times): self
    {
        $count = count($this->recorded);

        PHPUnit::assertEquals(
            $times,
            $count,
            "Expected inference to be called {$times} time(s), but was called {$count} time(s)."
        );

        return $this;
    }

    /**
     * Assert inference was never called.
     */
    public function assertNotCalled(): self
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Expected inference not to be called, but it was called ' . count($this->recorded) . ' time(s).'
        );

        return $this;
    }

    /**
     * Assert inference was called with specific messages.
     */
    public function assertCalledWith(string|array $messages): self
    {
        $found = collect($this->recorded)
            ->contains(function ($record) use ($messages) {
                if (is_string($messages)) {
                    return str_contains(json_encode($record['messages']), $messages);
                }
                return $record['messages'] === $messages;
            });

        PHPUnit::assertTrue(
            $found,
            'Expected inference to be called with specified messages, but it was not.'
        );

        return $this;
    }

    /**
     * Assert inference used a specific preset.
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
     * Assert inference used a specific model.
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
     * Assert inference was called with specific tools.
     */
    public function assertCalledWithTools(array $toolNames): self
    {
        $found = collect($this->recorded)
            ->contains(function ($record) use ($toolNames) {
                $recordedTools = array_column($record['tools'] ?? [], 'name');
                return empty(array_diff($toolNames, $recordedTools));
            });

        PHPUnit::assertTrue(
            $found,
            'Expected inference to be called with specified tools, but it was not.'
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

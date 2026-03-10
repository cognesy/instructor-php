<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a request for an inference operation, holding configuration parameters
 * such as messages, model, tools, tool choices, response format, and options,
 * and a cached context if applicable.
 */
class InferenceRequest
{
    public readonly InferenceRequestId $id;

    public readonly DateTimeImmutable $createdAt;

    public readonly DateTimeImmutable $updatedAt;

    protected Messages $messages;

    protected ToolDefinitions $tools;

    protected ToolChoice $toolChoice;

    protected ResponseFormat $responseFormat;

    protected string $model;

    protected array $options; // options may contain additional inference parameters like temperature, max tokens, etc.

    protected ?CachedInferenceContext $cachedContext;

    protected ResponseCachePolicy $responseCachePolicy;

    protected ?InferenceRetryPolicy $retryPolicy;

    public function __construct(
        ?Messages $messages = null,
        ?string $model = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
        ?CachedInferenceContext $cachedContext = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?InferenceRetryPolicy $retryPolicy = null,
        //
        ?InferenceRequestId $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? InferenceRequestId::generate();
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->cachedContext = $cachedContext ?? new CachedInferenceContext;

        $this->model = $model ?? '';
        $this->options = $options ?? [];
        $this->assertNoRetryPolicyInOptions($this->options);
        $this->retryPolicy = $retryPolicy;

        $this->tools = $tools ?? ToolDefinitions::empty();
        $this->toolChoice = $toolChoice ?? ToolChoice::empty();
        $this->responseFormat = $responseFormat ?? ResponseFormat::empty();
        $this->messages = $messages ?? Messages::empty();
        $this->responseCachePolicy = $responseCachePolicy ?? ResponseCachePolicy::None;
    }

    // ACCESSORS //////////////////////////////////////

    public function messages(): Messages
    {
        return $this->messages;
    }

    /**
     * Retrieves the model.
     *
     * @return string The model of the object.
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * Determines whether the content or resource is being streamed.
     *
     * @return bool True if streaming is enabled, false otherwise.
     */
    public function isStreamed(): bool
    {
        return $this->options['stream'] ?? false;
    }

    /**
     * Retrieves the configured tool definitions.
     *
     * @return ToolDefinitions The tool definitions configured on the request or cached context.
     */
    public function tools(): ToolDefinitions
    {
        return $this->tools;
    }

    /**
     * Retrieves the configured tool selection.
     *
     * @return ToolChoice The configured tool choice.
     */
    public function toolChoice(): ToolChoice
    {
        return $this->toolChoice;
    }

    /**
     * Retrieves the array of options configured for the current instance.
     *
     * @return array The array of options.
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Retrieves the cached context if available.
     *
     * @return CachedInferenceContext|null The cached context instance or null if not set.
     */
    public function cachedContext(): ?CachedInferenceContext
    {
        return $this->cachedContext;
    }

    public function responseCachePolicy(): ResponseCachePolicy
    {
        return $this->responseCachePolicy;
    }

    public function retryPolicy(): ?InferenceRetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * Retrieves the configured response format.
     */
    public function responseFormat(): ResponseFormat
    {
        return $this->responseFormat;
    }

    public function id(): InferenceRequestId
    {
        return $this->id;
    }

    // IS/HAS METHODS //////////////////////////////////////

    public function hasResponseFormat(): bool
    {
        $hasOwn = ! $this->responseFormat->isEmpty();
        $hasCached = $this->cachedContext !== null && ! $this->cachedContext->responseFormat()->isEmpty();

        return $hasOwn || $hasCached;
    }

    public function hasTextResponseFormat(): bool
    {
        if (! $this->hasResponseFormat()) {
            return false;
        }
        $ownType = ! $this->responseFormat->isEmpty() ? $this->responseFormat->type() : null;
        $hasCached = $this->cachedContext !== null && ! $this->cachedContext->responseFormat()->isEmpty();
        $cachedType = $hasCached ? $this->cachedContext->responseFormat()->type() : null;

        return $ownType === 'text' || $cachedType === 'text';
    }

    public function hasNonTextResponseFormat(): bool
    {
        if (! $this->hasResponseFormat()) {
            return false;
        }
        $ownType = ! $this->responseFormat->isEmpty() ? $this->responseFormat->type() : null;
        $hasCached = $this->cachedContext !== null && ! $this->cachedContext->responseFormat()->isEmpty();
        $cachedType = $hasCached ? $this->cachedContext->responseFormat()->type() : null;

        return ($ownType !== null && $ownType !== 'text') || ($cachedType !== null && $cachedType !== 'text');
    }

    public function hasTools(): bool
    {
        return ! $this->tools->isEmpty()
            || ! $this->cachedContext?->tools()->isEmpty();
    }

    public function hasToolChoice(): bool
    {
        return ! $this->toolChoice->isEmpty()
            || ! $this->cachedContext?->toolChoice()->isEmpty();
    }

    public function hasMessages(): bool
    {
        $hasOwn = ! $this->messages->isEmpty();
        $hasCached = $this->cachedContext !== null && ! $this->cachedContext->messages()->isEmpty();

        return $hasOwn || $hasCached;
    }

    public function hasModel(): bool
    {
        return ! empty($this->model);
    }

    public function hasOptions(): bool
    {
        return ! empty($this->options);
    }

    // MUTATORS //////////////////////////////////////

    public function with(
        ?Messages $messages = null,
        ?string $model = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
        ?CachedInferenceContext $cachedContext = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?InferenceRetryPolicy $retryPolicy = null,
    ): self {
        return new self(
            messages: $messages ?? $this->messages,
            model: $model ?? $this->model,
            tools: $tools ?? $this->tools,
            toolChoice: $toolChoice ?? $this->toolChoice,
            responseFormat: $responseFormat ?? $this->responseFormat,
            options: $options ?? $this->options,
            cachedContext: $cachedContext ?? $this->cachedContext,
            responseCachePolicy: $responseCachePolicy ?? $this->responseCachePolicy,
            retryPolicy: $retryPolicy ?? $this->retryPolicy,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable,
        );
    }

    public function withMessages(Messages $messages): self
    {
        return $this->with(messages: $messages);
    }

    public function withModel(string $model): self
    {
        return $this->with(model: $model);
    }

    public function withStreaming(bool $streaming): self
    {
        $options = $this->options;
        $options['stream'] = $streaming;

        return $this->with(options: $options);
    }

    public function withTools(ToolDefinitions $tools): self
    {
        return $this->with(tools: $tools);
    }

    public function withToolChoice(ToolChoice $toolChoice): self
    {
        return $this->with(toolChoice: $toolChoice);
    }

    public function withResponseFormat(ResponseFormat $responseFormat): self
    {
        return $this->with(responseFormat: $responseFormat);
    }

    public function withOptions(array $options): self
    {
        return $this->with(options: $options);
    }

    public function withCachedContext(?CachedInferenceContext $cachedContext): self
    {
        return $this->with(cachedContext: $cachedContext);
    }

    public function withResponseCachePolicy(ResponseCachePolicy $policy): self
    {
        return $this->with(responseCachePolicy: $policy);
    }

    public function withRetryPolicy(InferenceRetryPolicy $retryPolicy): self
    {
        return $this->with(retryPolicy: $retryPolicy);
    }

    /**
     * Returns a copy of the current object with cached context applied if it is available.
     * If no cached context is set, it returns the current instance unchanged.
     *
     * @return self A new instance with the cached context applied, or the current instance if no cache is set.
     */
    public function withCacheApplied(): self
    {
        if (! isset($this->cachedContext) || $this->cachedContext->isEmpty()) {
            return $this;
        }

        return new self(
            messages: $this->cachedContext->messages()->appendMessages($this->messages),
            model: $this->model,
            tools: $this->tools->isEmpty() ? $this->cachedContext->tools() : $this->tools,
            toolChoice: $this->toolChoice->isEmpty() ? $this->cachedContext->toolChoice() : $this->toolChoice,
            responseFormat: $this->responseFormat->isEmpty()
                ? $this->cachedContext->responseFormat()
                : $this->responseFormat,
            options: $this->options,
            cachedContext: new CachedInferenceContext,
            responseCachePolicy: $this->responseCachePolicy,
            retryPolicy: $this->retryPolicy,
            id: $this->id,
            createdAt: $this->createdAt,
        );
    }

    // SERIALIZATION /////////////////////////////////////

    /**
     * Converts the current object state into an associative array.
     *
     * @return array An associative array containing the object's properties and their values.
     */
    public function toArray(): array
    {
        return [
            'messages' => $this->messages->toArray(),
            'model' => $this->model,
            'tools' => $this->tools->toArray(),
            'tool_choice' => $this->toolChoice->toArray(),
            'response_format' => $this->responseFormat->toArray(),
            'options' => $this->options,
            'cached_context' => $this->cachedContext?->toArray(),
            'response_cache_policy' => $this->responseCachePolicy->value,
            'retry_policy' => $this->retryPolicy?->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $cachedContext = $data['cached_context'] ?? $data['cachedContext'] ?? null;
        $retryPolicy = $data['retry_policy'] ?? $data['retryPolicy'] ?? null;

        return new self(
            messages: self::messagesFromArray($data),
            model: $data['model'] ?? '',
            tools: self::toolsFromArray($data),
            toolChoice: self::toolChoiceFromArray($data),
            responseFormat: self::responseFormatFromArray($data),
            options: is_array($data['options'] ?? null) ? $data['options'] : [],
            cachedContext: self::cachedContextFromArray($cachedContext),
            responseCachePolicy: isset($data['response_cache_policy']) ? ResponseCachePolicy::from($data['response_cache_policy']) : null,
            retryPolicy: self::retryPolicyFromArray($retryPolicy),
        );
    }

    private static function messagesFromArray(array $data): Messages
    {
        $messages = $data['messages'] ?? [];

        return match (true) {
            $messages instanceof Messages => $messages,
            is_string($messages) => Messages::fromString($messages),
            is_array($messages) => Messages::fromAnyArray($messages),
            default => Messages::empty(),
        };
    }

    private static function toolsFromArray(array $data): ToolDefinitions
    {
        $tools = $data['tools'] ?? [];

        return match (true) {
            $tools instanceof ToolDefinitions => $tools,
            is_array($tools) => ToolDefinitions::fromArray($tools),
            default => ToolDefinitions::empty(),
        };
    }

    private static function toolChoiceFromArray(array $data): ToolChoice
    {
        $toolChoice = $data['tool_choice'] ?? $data['toolChoice'] ?? [];

        return match (true) {
            $toolChoice instanceof ToolChoice => $toolChoice,
            is_string($toolChoice), is_array($toolChoice) => ToolChoice::fromAny($toolChoice),
            default => ToolChoice::empty(),
        };
    }

    private static function responseFormatFromArray(array $data): ResponseFormat
    {
        $responseFormat = $data['response_format'] ?? $data['responseFormat'] ?? [];

        return match (true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
            default => ResponseFormat::empty(),
        };
    }

    private static function cachedContextFromArray(mixed $cachedContext): ?CachedInferenceContext
    {
        return match (true) {
            $cachedContext instanceof CachedInferenceContext => $cachedContext,
            is_array($cachedContext) => CachedInferenceContext::fromArray($cachedContext),
            default => null,
        };
    }

    private static function retryPolicyFromArray(mixed $retryPolicy): ?InferenceRetryPolicy
    {
        return match (true) {
            $retryPolicy instanceof InferenceRetryPolicy => $retryPolicy,
            is_array($retryPolicy) => InferenceRetryPolicy::fromArray($retryPolicy),
            default => null,
        };
    }

    // INTERNAL /////////////////////////////////////////////////

    private function assertNoRetryPolicyInOptions(array $options): void
    {
        if (! array_key_exists('retryPolicy', $options) && ! array_key_exists('retry_policy', $options)) {
            return;
        }

        throw new InvalidArgumentException('retryPolicy must be set via withRetryPolicy().');
    }
}

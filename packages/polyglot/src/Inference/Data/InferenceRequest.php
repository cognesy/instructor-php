<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Represents a request for an inference operation, holding configuration parameters
 * such as messages, model, tools, tool choices, response format, options, mode,
 * and a cached context if applicable.
 */
class InferenceRequest
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public readonly DateTimeImmutable $updatedAt;

    protected Messages $messages;
    protected array $tools;
    protected string|array $toolChoice;
    protected ResponseFormat $responseFormat;

    protected string $model;
    protected array $options; // options may contain additional inference parameters like temperature, max tokens, etc.
    protected ?OutputMode $mode;

    protected ?CachedContext $cachedContext;

    public function __construct(
        ?Messages $messages = null,
        ?string $model = null,
        ?array $tools = null,
        null|string|array $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
        ?OutputMode $mode = null,
        ?CachedContext $cachedContext = null,
        //
        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->cachedContext = $cachedContext ?? new CachedContext();

        $this->model = $model ?? '';
        $this->options = $options ?? [];
        $this->mode = $mode ?? OutputMode::Unrestricted;

        $this->tools = $tools ?? [];
        $this->toolChoice = $toolChoice ?? [];
        $this->responseFormat = match(true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            default => new ResponseFormat(),
        };

        $this->messages = $this->normalizeMessages($messages);
    }

    // ACCESSORS //////////////////////////////////////

    /**
     * Retrieves the array of messages.
     *
     * @return array Returns the array containing messages.
     */
    public function messages() : array {
        return $this->messages->toArray();
    }

    /**
     * Retrieves the model.
     *
     * @return string The model of the object.
     */
    public function model() : string {
        return $this->model;
    }

    /**
     * Determines whether the content or resource is being streamed.
     *
     * @return bool True if streaming is enabled, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    /**
     * Retrieves the list of tools based on the current mode.
     *
     * @return array An array of tools if the mode is set to Tools, otherwise an empty array.
     */
    public function tools() : array {
        return $this->tools;
     }

    /**
     * Retrieves the tool choice based on the current mode.
     *
     * @return string|array The tool choice if the mode is set to 'Tools', otherwise an empty array.
     */
    public function toolChoice() : string|array {
        return $this->toolChoice;
    }

    /**
     * Retrieves the array of options configured for the current instance.
     *
     * @return array The array of options.
     */
    public function options() : array {
        return $this->options;
    }

    /**
     * Retrieves the current mode of the object.
     *
     * @return OutputMode The current mode instance.
     */
    public function outputMode() : ?OutputMode {
        return $this->mode;
    }

    /**
     * Retrieves the cached context if available.
     *
     * @return CachedContext|null The cached context instance or null if not set.
     */
    public function cachedContext() : ?CachedContext {
        return $this->cachedContext;
    }

    /**
     * Retrieves the response format configuration based on the current mode.
     *
     * @return array An array representing the response format, varying depending on the mode.
     *               Includes schema details for JSON or JSON schema modes, or defaults to the
     *               existing response format configuration for other modes.
     */
    public function responseFormat() : ResponseFormat {
        return $this->responseFormat;
    }

    // IS/HAS METHODS //////////////////////////////////////

    public function hasResponseFormat() : bool {
        $hasOwn = !$this->responseFormat->isEmpty();
        $hasCached = $this->cachedContext !== null && !$this->cachedContext->responseFormat()->isEmpty();
        return $hasOwn || $hasCached;
    }

    public function hasTextResponseFormat() : bool {
        if (!$this->hasResponseFormat()) return false;
        $ownType = !$this->responseFormat->isEmpty() ? $this->responseFormat->type() : null;
        $hasCached = $this->cachedContext !== null && !$this->cachedContext->responseFormat()->isEmpty();
        $cachedType = $hasCached ? $this->cachedContext->responseFormat()->type() : null;
        return $ownType === 'text' || $cachedType === 'text';
    }

    public function hasNonTextResponseFormat() : bool {
        if (!$this->hasResponseFormat()) return false;
        $ownType = !$this->responseFormat->isEmpty() ? $this->responseFormat->type() : null;
        $hasCached = $this->cachedContext !== null && !$this->cachedContext->responseFormat()->isEmpty();
        $cachedType = $hasCached ? $this->cachedContext->responseFormat()->type() : null;
        return ($ownType !== null && $ownType !== 'text') || ($cachedType !== null && $cachedType !== 'text');
    }

    public function hasTools() : bool {
        return !empty($this->tools)
            || !empty($this->cachedContext?->tools());
    }

    public function hasToolChoice() : bool {
        return !empty($this->toolChoice)
            || !empty($this->cachedContext?->toolChoice());
    }

    public function hasMessages() : bool {
        return !empty($this->messages) || !$this->cachedContext->messages()->isEmpty();
    }

    public function hasModel() : bool {
        return !empty($this->model);
    }

    public function hasOptions() : bool {
        return !empty($this->options);
    }

    // MUTATORS //////////////////////////////////////

    public function with(
        string|array|null $messages = null,
        ?string $model = null,
        ?array $tools = null,
        string|array|null $toolChoice = null,
        ?array $responseFormat = null,
        ?array $options = null,
        ?OutputMode $mode = null,
        ?CachedContext $cachedContext = null,
    ) : self {
        return new self(
            messages: $messages ?? $this->messages,
            model: $model ?? $this->model,
            tools: $tools ?? $this->tools,
            toolChoice: $toolChoice ?? $this->toolChoice,
            responseFormat: $responseFormat ?? $this->responseFormat,
            options: $options ?? $this->options,
            mode: $mode ?? $this->mode,
            cachedContext: $cachedContext ?? $this->cachedContext,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withMessages(string|array $messages) : self {
        return $this->with(messages: $messages);
    }

    public function withModel(string $model) : self {
        return $this->with(model: $model);
    }

    public function withStreaming(bool $streaming) : self {
        $options = $this->options;
        $options['stream'] = $streaming;
        return $this->with(options: $options);
    }

    public function withTools(array $tools) : self {
        return $this->with(tools: $tools);
    }

    public function withToolChoice(string|array $toolChoice) : self {
        return $this->with(toolChoice: $toolChoice);
    }

    public function withResponseFormat(array $responseFormat) : self {
        return $this->with(responseFormat: $responseFormat);
    }

    public function withOptions(array $options) : self {
        return $this->with(options: $options);
    }

    public function withOutputMode(OutputMode $mode) : self {
        return $this->with(mode: $mode);
    }

    public function withCachedContext(?CachedContext $cachedContext) : self {
        return $this->with(cachedContext: $cachedContext);
    }

    /**
     * Returns a copy of the current object with cached context applied if it is available.
     * If no cached context is set, it returns the current instance unchanged.
     *
     * @return self A new instance with the cached context applied, or the current instance if no cache is set.
     */
    public function withCacheApplied() : self {
        if (!isset($this->cachedContext) || $this->cachedContext->isEmpty()) {
            return $this;
        }
        return new self(
            messages: $this->cachedContext->messages()->appendMessages($this->messages),
            model: $this->model,
            tools: empty($this->tools) ? $this->cachedContext->tools() : $this->tools,
            toolChoice: empty($this->toolChoice) ? $this->cachedContext->toolChoice() : $this->toolChoice,
            responseFormat: empty($this->responseFormat) ? $this->cachedContext->responseFormat() : $this->responseFormat,
            options: $this->options,
            mode: $this->mode,
            cachedContext: new CachedContext(),
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
    public function toArray() : array {
        return [
            'messages' => $this->messages,
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
            'response_format' => $this->responseFormat,
            'options' => $this->options,
            'mode' => $this->mode?->value,
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            messages: $data['messages'] ?? [],
            model: $data['model'] ?? '',
            tools: $data['tools'] ?? [],
            toolChoice: $data['tool_choice'] ?? [],
            responseFormat: $data['response_format'] ?? [],
            options: $data['options'] ?? [],
            mode: isset($data['mode']) ? OutputMode::from($data['mode']) : null,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function normalizeMessages(null|array|string|Messages $messages) : Messages {
        return match(true) {
            $messages instanceof Messages => $messages,
            is_string($messages) => Messages::fromString($messages),
            is_array($messages) => Messages::fromAnyArray($messages),
            default => new Messages(),
        };
    }
}

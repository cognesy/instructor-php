<?php
namespace Cognesy\Polyglot\LLM;

use Cognesy\Polyglot\LLM\Data\CachedContext;
use Cognesy\Polyglot\LLM\Enums\Mode;

/**
 * Represents a request for an inference operation, holding configuration parameters
 * such as messages, model, tools, tool choices, response format, options, mode,
 * and a cached context if applicable.
 */
class InferenceRequest
{
    public array $messages = [];
    public string $model = '';
    public array $tools = [];
    public string|array $toolChoice = [];
    public array $responseFormat = [];
    public array $options = [];
    public Mode $mode = Mode::Text;
    public ?CachedContext $cachedContext;

    public function __construct(
        string|array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
        ?CachedContext $cachedContext = null,
    ) {
        $this->cachedContext = $cachedContext;

        $this->model = $model;
        $this->options = $options;
        $this->mode = $mode;

        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;

        $this->withMessages($messages);
    }

    /**
     * Retrieves the array of messages.
     *
     * @return array Returns the array containing messages.
     */
    public function messages() : array {
        return $this->messages;
    }

    /**
     * Sets the messages for the current instance.
     *
     * @param string|array $messages The message content to set. Can be either a string, which is converted
     * into an array with a predefined structure, or an array provided directly.
     * @return self Returns the current instance with the updated messages.
     */
    public function withMessages(string|array $messages) : self {
        $this->messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            default => $messages,
        };
        return $this;
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
     * Sets the model to be used and returns the current instance.
     *
     * @param string $model The name of the model to set.
     * @return self The current instance with the updated model.
     */
    public function withModel(string $model) : self {
        $this->model = $model;
        return $this;
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
     * Sets the streaming option for the current instance.
     *
     * @param bool $streaming Whether to enable streaming.
     * @return self The current instance with the updated streaming option.
     */
    public function withStreaming(bool $streaming) : self {
        $this->options['stream'] = $streaming;
        return $this;
    }

    /**
     * Retrieves the list of tools based on the current mode.
     *
     * @return array An array of tools if the mode is set to Tools, otherwise an empty array.
     */
    public function tools() : array {
        return match($this->mode) {
            Mode::Tools => $this->tools,
            default => [],
        };
    }

    /**
     * Sets the tools to be used and returns the current instance.
     *
     * @param array $tools An array of tools to be assigned.
     * @return self The current instance with updated tools.
     */
    public function withTools(array $tools) : self {
        $this->tools = $tools;
        return $this;
    }

    /**
     * Retrieves the tool choice based on the current mode.
     *
     * @return string|array The tool choice if the mode is set to 'Tools', otherwise an empty array.
     */
    public function toolChoice() : string|array {
        return match($this->mode) {
            Mode::Tools => $this->toolChoice,
            default => [],
        };
    }

    /**
     * Sets the tool choice for the current instance.
     *
     * @param string|array $toolChoice The tool choice to be set, which can be a string or an array.
     * @return self Returns the current instance with the updated tool choice.
     */
    public function withToolChoice(string|array $toolChoice) : self {
        $this->toolChoice = $toolChoice;
        return $this;
    }

    /**
     * Retrieves the response format configuration based on the current mode.
     *
     * @return array An array representing the response format, varying depending on the mode.
     *               Includes schema details for JSON or JSON schema modes, or defaults to the
     *               existing response format configuration for other modes.
     */
    public function responseFormat() : array {
        return match($this->mode) {
            Mode::Json => [
                'type' => 'json_object',
                'schema' => $this->responseFormat['schema'] ?? [],
            ],
            Mode::JsonSchema => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $this->responseFormat['json_schema']['name'] ?? 'schema',
                    'schema' => $this->responseFormat['json_schema']['schema'] ?? [],
                    'strict' => $this->responseFormat['json_schema']['strict'] ?? true,
                ],
            ],
            Mode::Tools => [],
            Mode::MdJson => [],
            Mode::Text => [],
            default => $this->responseFormat,
        };
    }

    /**
     * Sets the response format configuration.
     *
     * @param array $responseFormat An associative array defining the response format settings.
     * @return self The current instance with the updated response format.
     */
    public function withResponseFormat(array $responseFormat) : self {
        $this->responseFormat = $responseFormat;
        return $this;
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
     * Sets the options for the current instance and returns it.
     *
     * @param array $options An associative array of options to configure the instance.
     * @return self The current instance with updated options.
     */
    public function withOptions(array $options) : self {
        $this->options = $options;
        return $this;
    }

    /**
     * Retrieves the current mode of the object.
     *
     * @return Mode The current mode instance.
     */
    public function mode() : Mode {
        return $this->mode;
    }

    /**
     * Sets the mode for the current instance and returns the updated instance.
     *
     * @param Mode $mode The mode to be set.
     * @return self The current instance with the updated mode.
     */
    public function withMode(Mode $mode) : self {
        $this->mode = $mode;
        return $this;
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
     * Sets the cached context for the current instance.
     *
     * @param CachedContext|null $cachedContext The cached context to be set, or null to clear it.
     * @return self The current instance for method chaining.
     */
    public function withCachedContext(?CachedContext $cachedContext) : self {
        $this->cachedContext = $cachedContext;
        return $this;
    }

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
            'mode' => $this->mode->value,
        ];
    }

    /**
     * Returns a cloned instance of the current object with cached context applied if available.
     * If no cached context is set, it returns the current instance unchanged.
     *
     * @return self A new instance with the cached context applied, or the current instance if no cache is set.
     */
    public function withCacheApplied() : self {
        if (!isset($this->cachedContext)) {
            return $this;
        }

        $cloned = clone $this;
        $cloned->messages = array_merge($this->cachedContext->messages, $this->messages);
        $cloned->tools = empty($this->tools) ? $this->cachedContext->tools : $this->tools;
        $cloned->toolChoice = empty($this->toolChoice) ? $this->cachedContext->toolChoice : $this->toolChoice;
        $cloned->responseFormat = empty($this->responseFormat) ? $this->cachedContext->responseFormat : $this->responseFormat;
        return $cloned;
    }
}

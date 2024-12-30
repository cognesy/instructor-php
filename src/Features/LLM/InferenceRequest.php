<?php
namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\CachedContext;

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

    public function messages() : array {
        return $this->messages;
    }

    public function withMessages(string|array $messages) : self {
        $this->messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            default => $messages,
        };
        return $this;
    }

    public function model() : string {
        return $this->model;
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function withModel(string $model) : self {
        $this->model = $model;
        return $this;
    }

    public function tools() : array {
        return match($this->mode) {
            Mode::Tools => $this->tools,
            default => [],
        };
    }

    public function withTools(array $tools) : self {
        $this->tools = $tools;
        return $this;
    }

    public function toolChoice() : string|array {
        return match($this->mode) {
            Mode::Tools => $this->toolChoice,
            default => [],
        };
    }

    public function withToolChoice(string|array $toolChoice) : self {
        $this->toolChoice = $toolChoice;
        return $this;
    }

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

    public function withResponseFormat(array $responseFormat) : self {
        $this->responseFormat = $responseFormat;
        return $this;
    }

    public function options() : array {
        return $this->options;
    }

    public function withOptions(array $options) : self {
        $this->options = $options;
        return $this;
    }

    public function mode() : Mode {
        return $this->mode;
    }

    public function withMode(Mode $mode) : self {
        $this->mode = $mode;
        return $this;
    }

    public function cachedContext() : ?CachedContext {
        return $this->cachedContext;
    }

    public function withCachedContext(?CachedContext $cachedContext) : self {
        $this->cachedContext = $cachedContext;
        return $this;
    }

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

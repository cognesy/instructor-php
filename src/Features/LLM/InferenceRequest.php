<?php
namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\CachedContext;

class InferenceRequest
{
//    public string $uuid = '';
//    public array $metadata = [];

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

        $this->messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            default => $messages,
        };

        if ($mode->is(Mode::Tools)) {
            $this->tools = $tools;
            $this->toolChoice = $toolChoice;
        } elseif ($mode->is(Mode::Json)) {
            $this->responseFormat = [
                'type' => 'json_object',
                'schema' => $responseFormat['schema'] ?? [],
            ];
        } elseif ($mode->is(Mode::JsonSchema)) {
            $this->responseFormat = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $responseFormat['json_schema']['name'] ?? 'schema',
                    'schema' => $responseFormat['json_schema']['schema'] ?? [],
                    'strict' => $responseFormat['json_schema']['strict'] ?? true,
                ],
            ];
        } elseif ($mode->is([Mode::Text, Mode::MdJson])) {
            $this->tools = [];
            $this->toolChoice = [];
            $this->responseFormat = [];
        }
    }

    public function toArray() : array {
        return [
//            'uuid' => $this->uuid,
//            'metadata' => $this->metadata,
            'messages' => $this->messages,
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
            'response_format' => $this->responseFormat,
            'options' => $this->options,
            'mode' => $this->mode->value,
        ];
    }
}

<?php
namespace Cognesy\Instructor\Features\Core\Data\Traits\RequestInfo;

use Cognesy\Instructor\Features\Core\Data\Example;
use Cognesy\LLM\LLM\Enums\Mode;

trait HandlesCreation
{
    public static function with(
        $messages = '',
        $input = '',
        $responseModel = '',
        $system = '',
        $prompt = '',
        $examples = [],
        $model = '',
        $maxRetries = 0,
        $options = [],
        $toolName = '',
        $toolDescription = '',
        $retryPrompt = '',
        $mode = Mode::Tools,
        $cachedContext = [],
    ) : static {
        $data = new static();
        $data->messages = $messages;
        $data->input = $input;
        $data->responseModel = $responseModel;
        $data->system = $system;
        $data->prompt = $prompt;
        $data->examples = $examples;
        $data->model = $model;
        $data->maxRetries = $maxRetries;
        $data->options = $options;
        $data->toolName = $toolName;
        $data->toolDescription = $toolDescription;
        $data->retryPrompt = $retryPrompt;
        $data->mode = $mode;
        $data->cachedContext = $cachedContext;
        return $data;
    }

    public static function fromArray(array $data) : static {
        return self::with(
            messages: $data['messages'] ?? '',
            input: $data['input'] ?? '',
            responseModel: $data['responseModel'] ?? '',
            system: $data['system'] ?? '',
            prompt: $data['prompt'] ?? '',
            examples: $data['examples'] ?? array_map(fn($example) => Example::fromArray($example), $data['examples'] ?? []),
            model: $data['model'] ?? '',
            maxRetries: $data['maxRetries'] ?? 0,
            options: $data['options'] ?? [],
            toolName: $data['toolName'] ?? '',
            toolDescription: $data['toolDescription'] ?? '',
            retryPrompt: $data['retryPrompt'] ?? '',
            mode: $data['mode'] ?? Mode::Tools,
            cachedContext: $data['cachedContext'] ?? [],
        );
    }
}

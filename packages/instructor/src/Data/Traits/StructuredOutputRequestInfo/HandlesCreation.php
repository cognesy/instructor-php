<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequestInfo;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\StructuredOutputConfig;

trait HandlesCreation
{
    public static function with(
        string|array $messages = '',
        string|array|object $input = '',
        string|array|object$responseModel = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
        string $model = '',
        array $options = [],
        array $cachedContext = [],
        StructuredOutputConfig $config = null,
    ) : static {
        $data = new static();
        $data->messages = $messages;
        $data->input = $input;
        $data->responseModel = $responseModel;
        $data->system = $system;
        $data->prompt = $prompt;
        $data->examples = $examples;
        $data->model = $model;
        $data->options = $options;
        $data->cachedContext = $cachedContext;
        $data->config = $config;
        return $data;
    }

    public static function fromArray(array $data) : static {
        return self::with(
            messages: $data['messages'] ?? '',
            input: $data['input'] ?? '',
            responseModel: $data['responseModel'] ?? '',
            system: $data['system'] ?? '',
            prompt: $data['prompt'] ?? '',
            examples: $data['examples']
                ?? array_map(fn($example) => Example::fromArray($example), $data['examples'] ?? []),
            model: $data['model'] ?? '',
            options: $data['options'] ?? [],
            cachedContext: $data['cachedContext'] ?? [],
            config: $data['config'] ?? [],
        );
    }
}

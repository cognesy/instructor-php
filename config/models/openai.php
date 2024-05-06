<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'openai:gpt-4-turbo',
        context: [
            'label' => 'OpenAI GPT4 Turbo',
            'type' => 'gpt4',
            'name' => 'gpt-4-turbo',
            'maxTokens' => 128_000,
            'contextSize' => 128_000,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'user',
                'assistant' => 'assistant',
                'system' => 'system'
            ],
        ],
    );

    $config->declare(
        class: ModelParams::class,
        name: 'openai:gpt-4',
        context: [
            'label' => 'OpenAI GPT 4',
            'type' => 'gpt4',
            'name' => 'gpt-4',
            'maxTokens' => 8_192,
            'contextSize' => 8_192,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'user',
                'assistant' => 'assistant',
                'system' => 'system'
            ],
        ],
    );

    $config->declare(
        class: ModelParams::class,
        name: 'openai:gpt-4-32k',
        context: [
            'label' => 'OpenAI GPT 4 32k',
            'type' => 'gpt4',
            'name' => 'gpt-4-32k',
            'maxTokens' => 32_768,
            'contextSize' => 32_768,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'user',
                'assistant' => 'assistant',
                'system' => 'system'
            ],
        ],
    );

    $config->declare(
        class: ModelParams::class,
        name: 'openai:gpt-3.5-turbo',
        context: [
            'label' => 'OpenAI GPT 3.5 Turbo',
            'type' => 'gpt35',
            'name' => 'gpt-3.5-turbo',
            'maxTokens' => 4_096,
            'contextSize' => 16_385,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'user',
                'assistant' => 'assistant',
                'system' => 'system'
            ],
        ],
    );

    return $config;
};

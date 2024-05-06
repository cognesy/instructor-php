<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'anthropic:claude-3-haiku',
        context: [
            'label' => 'Claude 3 Haiku',
            'type' => 'claude3',
            'name' => 'claude-3-haiku-20240307',
            'maxTokens' => 4096,
            'contextSize' => 200_000,
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
        name: 'anthropic:claude-3-sonnet',
        context: [
            'label' => 'Claude 3 Sonnet',
            'type' => 'claude3',
            'name' => 'claude-3-sonnet-20240229',
            'maxTokens' => 4096,
            'contextSize' => 200_000,
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
        name: 'anthropic:claude-3-opus',
        context: [
            'label' => 'Claude 3 Opus',
            'type' => 'claude3',
            'name' => 'claude-3-opus-20240229',
            'maxTokens' => 4096,
            'contextSize' => 200_000,
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

<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'mistral-mistral-7b',
        context: [
            'label' => 'Mistral Mistral 7B',
            'type' => 'mistral',
            'name' => 'open-mistral-7b',
            'maxTokens' => 32_000,
            'contextSize' => 32_000,
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
        name: 'mistral-mixtral-8x7b',
        context: [
            'label' => 'Mistral Mixtral 8x7B',
            'type' => 'mixtral',
            'name' => 'open-mixtral-8x7b',
            'maxTokens' => 32_000,
            'contextSize' => 32_000,
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
        name: 'mistral-mixtral-8x22b',
        context: [
            'label' => 'Mistral Mixtral 8x22B',
            'type' => 'mixtral',
            'name' => 'open-mixtral-8x22b',
            'maxTokens' => 64_000,
            'contextSize' => 64_000,
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
        name: 'mistral-small',
        context: [
            'label' => 'Mistral Small',
            'type' => 'mistral',
            'name' => 'mistral-small-latest',
            'maxTokens' => 32_000,
            'contextSize' => 32_000,
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
        name: 'mistral-medium',
        context: [
            'label' => 'Mistral Medium',
            'type' => 'mistral',
            'name' => 'mistral-medium-latest',
            'maxTokens' => 32_000,
            'contextSize' => 32_000,
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
        name: 'mistral-large',
        context: [
            'label' => 'Mistral Large',
            'type' => 'mistral',
            'name' => 'mistral-large-latest',
            'maxTokens' => 32_000,
            'contextSize' => 32_000,
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

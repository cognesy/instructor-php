<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'groq:llama3-8b',
        context: [
            'label' => 'GroQ LLaMA3 8B',
            'type' => 'llama3',
            'name' => 'llama3-8b-8192',
            'maxTokens' => 8192,
            'contextSize' => 8192,
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
        name: 'groq:llama3-70b',
        context: [
            'label' => 'GroQ LLaMA3 70B',
            'type' => 'llama3',
            'name' => 'llama3-70b-8192',
            'maxTokens' => 8192,
            'contextSize' => 8192,
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
        name: 'groq:mixtral-8x7b',
        context: [
            'label' => 'GroQ Mixtral 8x7B',
            'type' => 'mixtral',
            'name' => 'mixtral-8x7b-32768',
            'maxTokens' => 32768,
            'contextSize' => 32768,
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
        name: 'groq:gemma-7b',
        context: [
            'label' => 'GroQ Gemma 7B',
            'type' => 'gemma',
            'name' => 'gemma-7b-it',
            'maxTokens' => 8192,
            'contextSize' => 8192,
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

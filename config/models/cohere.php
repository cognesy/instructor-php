<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'cohere:command-r-plus',
        context: [
            'label' => 'Cohere Command R Plus',
            'type' => 'cohere',
            'name' => 'command-r-plus',
            'maxTokens' => 4096,
            'contextSize' => 128_000,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'USER',
                'assistant' => 'CHATBOT',
                'system' => 'USER'
            ],
        ],
    );

    $config->declare(
        class: ModelParams::class,
        name: 'cohere:command-r',
        context: [
            'label' => 'Cohere Command R',
            'type' => 'cohere',
            'name' => 'command-r',
            'maxTokens' => 4096,
            'contextSize' => 128_000,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'USER',
                'assistant' => 'CHATBOT',
                'system' => 'USER'
            ],
        ],
    );

    $config->declare(
        class: ModelParams::class,
        name: 'cohere:command',
        context: [
            'label' => 'Cohere Command',
            'type' => 'cohere',
            'name' => 'command',
            'maxTokens' => 4096,
            'contextSize' => 4096,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'USER',
                'assistant' => 'CHATBOT',
                'system' => 'USER'
            ],
        ],
    );

    $config->declare(
        class: ModelParams::class,
        name: 'cohere:command-light',
        context: [
            'label' => 'Cohere Command Light',
            'type' => 'cohere',
            'name' => 'command-light',
            'maxTokens' => 4096,
            'contextSize' => 4096,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'USER',
                'assistant' => 'CHATBOT',
                'system' => 'USER'
            ],
        ],
    );

    return $config;
};

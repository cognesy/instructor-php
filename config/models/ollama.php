<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'ollama:llama2',
        context: [
            'label' => 'Ollama LLaMA2',
            'type' => 'llama2',
            'name' => 'llama2:latest',
            'maxTokens' => 4096,
            'contextSize' => 4096,
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
        name: 'ollama:llama3',
        context: [
            'label' => 'Ollama LLaMA2',
            'type' => 'llama2',
            'name' => 'llama3:latest',
            'maxTokens' => 4096,
            'contextSize' => 4096,
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

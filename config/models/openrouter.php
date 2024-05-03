<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'openrouter-llama3',
        context: [
            'label' => 'OpenRouter LLaMA3 8B',
            'type' => 'llama3',
            'name' => 'meta-llama/llama-3-8b-instruct:extended',
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
        name: 'openrouter-mixtral-8x7b',
        context: [
            'label' => 'OpenRouter Mixtral 8x7b',
            'type' => 'mixtral',
            'name' => 'mistralai/mixtral-8x7b',
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
        name: 'openrouter-mistral-7b',
        context: [
            'label' => 'OpenRouter Mistral 7B Instruct',
            'type' => 'mistral',
            'name' => 'mistralai/mistral-7b-instruct:free',
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

    return $config;
};

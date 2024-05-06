<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'azure:gpt-3.5-turbo',
        context: [
            'label' => 'Azure GPT 3.5 Turbo',
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

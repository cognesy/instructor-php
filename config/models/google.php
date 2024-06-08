<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'google:gemini-1.5-flash',
        context: [
            'label' => 'Google Gemini 1.5 Flash',
            'type' => 'gemini',
            'name' => 'gemini-1.5-flash',
            'maxTokens' => 4096,
            'contextSize' => 128_000,
            'inputCost' => 1,
            'outputCost' => 1,
            'roleMap' => [
                'user' => 'user',
                'assistant' => 'model',
                'system' => 'user'
            ],
        ],
    );

    return $config;
};

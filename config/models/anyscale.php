<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'anyscale:mixtral-8x7b',
        context: [
            'label' => 'Anyscale Mixtral 8x7B',
            'type' => 'mixtral',
            'name' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
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

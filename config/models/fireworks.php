<?php

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Configuration\Configuration;

return function(Configuration $config) : Configuration {
    $config->declare(
        class: ModelParams::class,
        name: 'fireworks:mixtral-8x7b',
        context: [
            'label' => 'Fireworks Mixtral 8x7B',
            'type' => 'mixtral',
            'name' => 'accounts/fireworks/models/mixtral-8x7b-instruct',
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

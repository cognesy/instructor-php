<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('applies request overrides over runtime defaults in OpenAI request body', function () {
    $config = new LLMConfig(
        model: 'runtime-default-model',
        driver: 'openai',
        options: [
            'temperature' => 0.2,
            'presence_penalty' => 0.1,
            'stream' => false,
        ],
    );

    $body = (new OpenAIBodyFormat($config, new OpenAIMessageFormat()))
        ->toRequestBody(new InferenceRequest(
            messages: 'Hello',
            model: 'request-model',
            options: [
                'temperature' => 0.8,
                'stream' => true,
                'top_p' => 0.7,
            ],
        ));

    expect($body['model'])->toBe('request-model');
    expect($body['temperature'])->toBe(0.8);
    expect($body['presence_penalty'])->toBe(0.1);
    expect($body['top_p'])->toBe(0.7);
    expect($body['stream'])->toBeTrue();
    expect($body['stream_options']['include_usage'])->toBeTrue();
});

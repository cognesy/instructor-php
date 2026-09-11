<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\LLMConfig;

it('hydrates known LLM configuration fields and ignores unknown input', function () {
    $config = LLMConfig::fromArray([
        'driver' => 'openai',
        'model' => 'gpt-test',
        'maxTokens' => '2048',
        'futureField' => 'ignored',
        'extensionData' => ['ignored' => true],
    ]);

    expect($config->driver)->toBe('openai')
        ->and($config->model)->toBe('gpt-test')
        ->and($config->maxTokens)->toBe(2048)
        ->and($config->toArray())->not->toHaveKeys(['futureField', 'extensionData']);
});

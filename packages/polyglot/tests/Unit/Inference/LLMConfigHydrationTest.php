<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\LLMConfig;

it('hydrates known LLM configuration fields and ignores unknown input', function () {
    $config = LLMConfig::fromArray([
        'driver' => 'openai',
        'model' => 'gpt-test',
        'maxTokens' => '2048',
        'allowLossyFallback' => true,
        'futureField' => 'ignored',
        'extensionData' => ['ignored' => true],
    ]);

    expect($config->driver)->toBe('openai')
        ->and($config->model)->toBe('gpt-test')
        ->and($config->maxTokens)->toBe(2048)
        ->and($config->allowLossyFallback)->toBeTrue()
        ->and($config->toArray())->not->toHaveKeys(['futureField', 'extensionData']);
});

it('defaults lossy fallback to false and rejects non-boolean values', function () {
    expect((new LLMConfig)->allowLossyFallback)->toBeFalse()
        ->and(fn () => LLMConfig::fromArray(['allowLossyFallback' => 'true']))
        ->toThrow(InvalidArgumentException::class, 'allowLossyFallback');
});

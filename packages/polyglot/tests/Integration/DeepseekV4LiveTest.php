<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

it('completes a DeepSeek V4 Pro thinking request', function () {
    $apiKey = Env::get('DEEPSEEK_API_KEY');

    if (Env::get('POLYGLOT_DEEPSEEK_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_DEEPSEEK_LIVE=1 to run the DeepSeek V4 live smoke.');
    }

    if (! is_string($apiKey) || $apiKey === '') {
        test()->markTestSkipped('DEEPSEEK_API_KEY is not configured.');
    }

    $response = Inference::fromConfig(new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: $apiKey,
        endpoint: '/chat/completions',
        model: 'deepseek-v4-pro',
        maxTokens: 64,
        driver: 'deepseek',
    ))
        ->withMessages(Messages::fromString('Reply with exactly the token V4_OK.'))
        ->withOptions([
            'thinking' => ['type' => 'enabled'],
            'reasoning_effort' => 'low',
        ])
        ->response();

    expect($response->content())->toContain('V4_OK')
        ->and($response->reasoningContent())->not->toBe('');
})->group('deepseek-live');

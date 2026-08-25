<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

it('completes a Qwen3.8-Max request through the bundled preset', function () {
    $apiKey = Env::get('QWEN_API_KEY');

    if (Env::get('POLYGLOT_QWEN_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_QWEN_LIVE=1 to run the Qwen live smoke.');
    }

    if (! is_string($apiKey) || trim($apiKey) === '') {
        test()->markTestSkipped('QWEN_API_KEY is not configured.');
    }

    $response = Inference::using('qwen')
        ->with(
            messages: Messages::fromString('Reply with exactly the token QWEN_OK.'),
            options: [
                'enable_thinking' => false,
                'max_tokens' => 32,
            ],
        )
        ->get();

    expect($response)->toContain('QWEN_OK');
})->group('qwen-live');

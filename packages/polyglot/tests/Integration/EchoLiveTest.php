<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

it('writes in the requested persona via the Echo preset', function () {
    $apiKey = Env::get('ECHO_API_KEY');

    if (Env::get('POLYGLOT_ECHO_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_ECHO_LIVE=1 to run the Echo live smoke.');
    }

    if (! is_string($apiKey) || $apiKey === '') {
        test()->markTestSkipped('ECHO_API_KEY is not configured.');
    }

    $response = Inference::fromConfig(LLMConfig::fromPreset('echo')->withOverrides(['maxTokens' => 2048]))
        ->withMessages(Messages::fromString('Write one sentence about rain.'))
        ->withOptions(['persona' => 'Emily Dickinson'])
        ->response();

    expect($response->message()->content()->toString())->not->toBe('');
})->group('echo-live');

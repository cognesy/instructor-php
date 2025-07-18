<?php

use Cognesy\Config\Dsn;

it('can be created from constructor', function () {
    $dsn = Dsn::fromString('provider=openai, model=gpt-4');
    expect($dsn)->toBeInstanceOf(Dsn::class);
});

it('can be created from static method', function () {
    $dsn = Dsn::fromString('provider=openai, model=gpt-4');
    expect($dsn)->toBeInstanceOf(Dsn::class);
});

it('can be created with empty string', function () {
    $dsn = Dsn::fromString('');
    expect($dsn->params())->toBeEmpty();
});

it('parses basic parameters', function () {
    $dsn = Dsn::fromString('provider=openai, model=gpt-4, temperature=0.7');

    expect($dsn->hasParam('provider'))->toBeTrue();
    expect($dsn->param('provider'))->toBe('openai');
    expect($dsn->param('model'))->toBe('gpt-4');
    expect($dsn->param('temperature'))->toBe('0.7');
});

it('returns all parameters', function () {
    $dsn = Dsn::fromString('provider=openai, model=gpt-4');

    expect($dsn->params())->toBe([
        'provider' => 'openai',
        'model' => 'gpt-4'
    ]);
});

it('handles missing parameters with default values', function () {
    $dsn = Dsn::fromString('provider=openai');

    expect($dsn->param('model', 'gpt-3.5-turbo'))->toBe('gpt-3.5-turbo');
    expect($dsn->param('nonexistent'))->toBeNull();
    expect($dsn->param('nonexistent', 'default'))->toBe('default');
});

it('checks if parameters exist', function () {
    $dsn = Dsn::fromString('provider=openai');

    expect($dsn->hasParam('provider'))->toBeTrue();
    expect($dsn->hasParam('nonexistent'))->toBeFalse();
});

it('replaces environment variables in curly braces', function () {
    // Set environment variable for testing
    putenv('TEST_API_KEY=sk-test123');

    $dsn = Dsn::fromString('provider=openai, api_key={TEST_API_KEY}');

    expect($dsn->param('api_key'))->toBe('sk-test123');

    // Clean up
    putenv('TEST_API_KEY');
});

it('leaves template variables unreplaced when not in environment', function () {
    $dsn = Dsn::fromString('provider=openai, api_key={NONEXISTENT_KEY}');

    expect($dsn->param('api_key'))->toBe('{NONEXISTENT_KEY}');
});

it('handles dot notation for nested parameters', function () {
    $dsn = Dsn::fromString('provider=azure, metadata.apiVersion=2023-05-15, metadata.resourceName=instructor-dev');

    expect($dsn->params())->toBe([
        'provider' => 'azure',
        'metadata' => [
            'apiVersion' => '2023-05-15',
            'resourceName' => 'instructor-dev'
        ]
    ]);
});

it('allows accessing nested parameters with dot notation', function () {
    $dsn = Dsn::fromString('provider=azure, metadata.apiVersion=2023-05-15');

    expect($dsn->hasParam('metadata.apiVersion'))->toBeTrue();
    expect($dsn->param('metadata.apiVersion'))->toBe('2023-05-15');
});

it('handles deeply nested parameters', function () {
    $dsn = Dsn::fromString('provider=azure, config.http.timeout.connect=30, config.http.timeout.read=60');

    expect($dsn->params())->toBe([
        'provider' => 'azure',
        'config' => [
            'http' => [
                'timeout' => [
                    'connect' => '30',
                    'read' => '60'
                ]
            ]
        ]
    ]);
});

it('handles spaces in values', function () {
    $dsn = Dsn::fromString('provider=openai, model=My Custom Model, description=This is a test');

    expect($dsn->param('description'))->toBe('This is a test');
});

it('ignores malformed pairs', function () {
    // NOTE: Fix needed in code - isPair() logic is currently inverted
    $dsn = Dsn::fromString('provider=openai, malformed_value, model=gpt-4');

    expect($dsn->params())->toBe([
        'provider' => 'openai',
        'model' => 'gpt-4'
    ]);
});

it('trims whitespace from keys and values', function () {
    $dsn = Dsn::fromString('  provider = openai ,  model = gpt-4  ');

    expect($dsn->params())->toBe([
        'provider' => 'openai',
        'model' => 'gpt-4'
    ]);
});

it('handles empty values', function () {
    $dsn = Dsn::fromString('provider=openai, model=, api_key=');

    expect($dsn->param('model'))->toBe('');
    expect($dsn->param('api_key'))->toBe('');
});

<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Core\SensitiveDataRedactor;

it('flags credential-bearing keys as sensitive', function (string $key) {
    expect(SensitiveDataRedactor::isSensitiveKey($key))->toBeTrue();
})->with([
    'apiKey', 'api_key', 'API-KEY',
    'Authorization', 'proxy-authorization',
    'token', 'accessToken', 'refresh_token', 'x-auth-token',
    'secret', 'client_secret',
    'password',
    'cookie', 'Set-Cookie',
]);

it('does not flag ordinary keys as sensitive', function (string $key) {
    expect(SensitiveDataRedactor::isSensitiveKey($key))->toBeFalse();
})->with([
    'model', 'temperature', 'endpoint', 'content-type', 'user-agent',
]);

// The substring heuristic deliberately errs on the safe side:
// any key containing token/secret/password is masked, even benign ones.
it('over-matches token-like keys by design', function () {
    expect(SensitiveDataRedactor::isSensitiveKey('maxTokens'))->toBeTrue();
});

it('recursively masks values under sensitive keys', function () {
    $redacted = SensitiveDataRedactor::redactValues([
        'model' => 'gpt-x',
        'apiKey' => 'sk-live-123',
        'nested' => [
            'Authorization' => 'Bearer abc',
            'temperature' => 0.7,
        ],
    ]);

    expect($redacted)->toBe([
        'model' => 'gpt-x',
        'apiKey' => SensitiveDataRedactor::MASK,
        'nested' => [
            'Authorization' => SensitiveDataRedactor::MASK,
            'temperature' => 0.7,
        ],
    ]);
});

it('masks header values whose name is sensitive without recursing', function () {
    $redacted = SensitiveDataRedactor::redactHeaders([
        'Authorization' => 'Bearer abc',
        'X-Api-Key' => 'secret',
        'Content-Type' => 'application/json',
    ]);

    expect($redacted)->toBe([
        'Authorization' => SensitiveDataRedactor::MASK,
        'X-Api-Key' => SensitiveDataRedactor::MASK,
        'Content-Type' => 'application/json',
    ]);
});

it('masks userinfo and sensitive query params in a URL', function () {
    $url = 'https://user:pass@api.example.com/v1?api_key=secret&token=abc&q=hello';
    $redacted = SensitiveDataRedactor::redactUrl($url);

    expect($redacted)->toContain('q=hello')
        ->and($redacted)->toContain('api_key=%5BREDACTED%5D')
        ->and($redacted)->toContain('token=%5BREDACTED%5D')
        ->and($redacted)->toContain(SensitiveDataRedactor::MASK . '@api.example.com')
        ->and($redacted)->not->toContain('secret')
        ->and($redacted)->not->toContain(':pass@');
});

it('leaves a URL without query or userinfo unchanged', function () {
    $url = 'https://api.example.com/v1/chat/completions';
    expect(SensitiveDataRedactor::redactUrl($url))->toBe($url);
});

it('redacts a URL embedded inside exception text', function () {
    $text = 'Failed calling https://api.example.com/v1?api_key=secret now';
    $redacted = SensitiveDataRedactor::redactUrlInText($text, 'https://api.example.com/v1?api_key=secret');

    expect($redacted)->not->toContain('api_key=secret')
        ->and($redacted)->toContain('api_key=%5BREDACTED%5D');
});

it('summarizes field types without exposing any value', function () {
    $summary = SensitiveDataRedactor::summarizeFieldTypes([
        'apiKey' => 'sk-live-secret',
        'maxTokens' => 1024,
        'options' => ['a' => 1],
    ]);

    expect($summary)->not->toContain('sk-live-secret')
        ->and($summary)->toContain('apiKey')
        ->and($summary)->toContain('string')
        ->and($summary)->toContain('maxTokens')
        ->and($summary)->toContain('int')
        ->and($summary)->toContain('array');
});

it('redacts every url inside a free-text message, preserving trailing punctuation', function () {
    $message = 'Failed calling https://user:pass@api.example.com/v1?api_key=secret&q=ok. Retry soon.';
    $redacted = SensitiveDataRedactor::redactMessage($message);

    expect($redacted)->not->toContain('secret')
        ->and($redacted)->not->toContain('user:pass@')
        ->and($redacted)->toContain('q=ok')
        ->and($redacted)->toContain('api_key=%5BREDACTED%5D')
        // sentence punctuation after the URL is preserved
        ->and($redacted)->toContain('. Retry soon.');
});

it('leaves a message with no urls unchanged', function () {
    $message = 'Provider rejected the request: invalid model.';
    expect(SensitiveDataRedactor::redactMessage($message))->toBe($message);
});

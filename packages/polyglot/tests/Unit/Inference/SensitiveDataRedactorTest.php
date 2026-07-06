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

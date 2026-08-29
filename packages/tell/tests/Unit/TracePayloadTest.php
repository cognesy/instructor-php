<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Observability\TracePayload;

it('omits execution payloads and always redacts credential fields', function (): void {
    $payload = TracePayload::sanitize([
        'messagePayload' => [['role' => 'user', 'content' => 'private prompt']],
        'args' => ['path' => '/private/file'],
        'inputTokens' => 12,
        'api_key' => 'secret-value',
        'client_secret' => 'client-secret-value',
        'private_key' => 'private-key-value',
    ], includePayloads: false, maxStringLength: 4096);

    expect($payload)->toBe([
        'messagePayload' => '[omitted]',
        'args' => '[omitted]',
        'inputTokens' => 12,
        'api_key' => '[redacted]',
        'client_secret' => '[redacted]',
        'private_key' => '[redacted]',
    ]);
});

it('keeps opted-in payloads bounded while retaining credential redaction', function (): void {
    $payload = TracePayload::sanitize([
        'prompt' => str_repeat('ą', 300),
        'authorization' => 'Bearer secret',
    ], includePayloads: true, maxStringLength: 256);

    expect($payload['prompt'])->toHaveLength(259)
        ->and($payload['prompt'])->toEndWith('...')
        ->and($payload['authorization'])->toBe('[redacted]');
});

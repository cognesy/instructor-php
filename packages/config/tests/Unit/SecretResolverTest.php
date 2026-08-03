<?php

declare(strict_types=1);

namespace Cognesy\Config\Tests\Unit;

use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\ArraySecretSource;
use Cognesy\Config\Secrets\SecretResolver;

it('resolves the first configured secret with safe provenance', function (): void {
    $resolver = new SecretResolver(
        new ArraySecretSource('runtime', ['API_KEY' => 'runtime-secret']),
        new ArraySecretSource('fallback', ['API_KEY' => 'fallback-secret']),
    );

    $resolved = $resolver->resolve('API_KEY');

    expect($resolved?->value())->toBe('runtime-secret')
        ->and($resolved?->toArray())->toBe([
            'name' => 'API_KEY',
            'configured' => true,
            'source' => 'runtime',
        ])
        ->and(json_encode($resolved?->toArray(), JSON_THROW_ON_ERROR))->not->toContain('runtime-secret')
        ->and(print_r($resolved, true))->not->toContain('runtime-secret')
        ->and(print_r($resolver, true))->not->toContain('runtime-secret')
        ->and(print_r($resolver, true))->not->toContain('fallback-secret');
});

it('returns null when no secret source contains the requested name', function (): void {
    $resolver = new SecretResolver(new ArraySecretSource('empty', []));

    expect($resolver->resolve('MISSING_KEY'))->toBeNull();
});

it('injects a resolver into environment templates without global Env state', function (): void {
    $resolver = new SecretResolver(new ArraySecretSource('application', ['API_KEY' => 'injected-secret']));
    $template = new EnvTemplate($resolver);

    expect($template->resolveString('Bearer ${API_KEY}'))->toBe('Bearer injected-secret');
});

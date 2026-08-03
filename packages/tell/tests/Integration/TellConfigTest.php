<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Runtime\TellConfig;

it('uses privacy-preserving trace defaults without a config file', function (): void {
    $config = TellConfig::fromFile(tellTestFactory()->paths()->configFile);

    expect($config->executionTraces)->toBeTrue()
        ->and($config->includePayloads)->toBeFalse()
        ->and($config->maxStringLength)->toBe(4096);
});

it('loads explicit observability settings', function (): void {
    $path = tellTestFactory()->paths()->configFile;
    file_put_contents($path, json_encode([
        'schema' => 'tell.config.v1',
        'observability' => [
            'executionTraces' => false,
            'includePayloads' => true,
            'maxStringLength' => 2048,
        ],
    ], JSON_THROW_ON_ERROR));

    $config = TellConfig::fromFile($path);

    expect($config->executionTraces)->toBeFalse()
        ->and($config->includePayloads)->toBeTrue()
        ->and($config->maxStringLength)->toBe(2048);
});

it('rejects unknown config keys instead of silently ignoring typos', function (): void {
    $path = tellTestFactory()->paths()->configFile;
    file_put_contents($path, '{"observability":{"executionTrace":false}}');

    expect(static fn (): TellConfig => TellConfig::fromFile($path))
        ->toThrow(RuntimeException::class, 'unknown key(s): executionTrace');
});

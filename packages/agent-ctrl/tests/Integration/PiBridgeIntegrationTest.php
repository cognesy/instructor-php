<?php declare(strict_types=1);

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Builder\PiBridgeBuilder;
use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Config\Env;

/**
 * Integration smoke tests for Pi CLI bridge.
 *
 * These tests require:
 * - `pi` binary in PATH (npm install -g @mariozechner/pi-coding-agent)
 * - OPENAI_API_KEY in monorepo root .env or environment
 *
 * Run selectively: vendor/bin/pest packages/agent-ctrl/tests/Integration/PiBridgeIntegrationTest.php
 */

function piIsAvailable(): bool
{
    return CliBinaryGuard::isAvailable('pi');
}

function piOpenAiApiKey(): ?string
{
    $apiKey = Env::get('OPENAI_API_KEY');

    return $apiKey !== '' ? $apiKey : null;
}

function skipIfPiUnavailable(): void
{
    if (!piIsAvailable()) {
        test()->markTestSkipped('pi binary not found in PATH');
    }
    if (piOpenAiApiKey() === null) {
        test()->markTestSkipped('No OPENAI_API_KEY configured');
    }
}

function piBridge(): PiBridgeBuilder
{
    $apiKey = piOpenAiApiKey();
    if ($apiKey === null) {
        test()->markTestSkipped('No OPENAI_API_KEY configured');
    }

    return AgentCtrl::pi()
        ->withProvider('openai')
        ->withModel('gpt-4o-mini')
        ->withApiKey($apiKey);
}

it('executes a basic prompt via pi and returns response', function () {
    skipIfPiUnavailable();

    $response = piBridge()
        ->ephemeral()
        ->withTimeout(30)
        ->execute('Reply with exactly the word "pong" and nothing else.');

    expect($response)->toBeInstanceOf(AgentResponse::class)
        ->and($response->agentType)->toBe(AgentType::Pi)
        ->and($response->exitCode)->toBe(0)
        ->and($response->isSuccess())->toBeTrue()
        ->and($response->text())->not->toBeEmpty()
        ->and(strtolower(trim($response->text())))->toContain('pong');
})->group('integration');

it('streams text via pi callbacks', function () {
    skipIfPiUnavailable();

    $textChunks = [];

    $response = piBridge()
        ->ephemeral()
        ->withTimeout(30)
        ->onText(function (string $text) use (&$textChunks) {
            $textChunks[] = $text;
        })
        ->executeStreaming('Reply with exactly the word "hello" and nothing else.');

    expect($response->isSuccess())->toBeTrue()
        ->and($textChunks)->not->toBeEmpty()
        ->and(implode('', $textChunks))->not->toBeEmpty();
})->group('integration');

it('returns session id from pi execution', function () {
    skipIfPiUnavailable();

    $response = piBridge()
        ->withTimeout(30)
        ->execute('Reply with "ok".');

    expect($response->sessionId())->not->toBeNull();
})->group('integration');

it('returns usage data from pi execution', function () {
    skipIfPiUnavailable();

    $response = piBridge()
        ->ephemeral()
        ->withTimeout(30)
        ->execute('Reply with "ok".');

    $usage = $response->usage();
    expect($usage)->not->toBeNull()
        ->and($usage->input)->toBeGreaterThan(0)
        ->and($usage->output)->toBeGreaterThan(0);
})->group('integration');

it('uses read-only tools restriction', function () {
    skipIfPiUnavailable();

    $response = piBridge()
        ->ephemeral()
        ->withTools(['read', 'grep', 'find', 'ls'])
        ->withTimeout(30)
        ->execute('Reply with "ok".');

    expect($response->isSuccess())->toBeTrue();
})->group('integration');

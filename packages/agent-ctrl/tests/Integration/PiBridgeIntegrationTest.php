<?php declare(strict_types=1);

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Config\Env;

/**
 * Integration smoke tests for Pi CLI bridge.
 *
 * These tests require:
 * - `pi` binary in PATH (npm install -g @mariozechner/pi-coding-agent)
 * - ANTHROPIC_API_KEY or OPENAI_API_KEY in monorepo root .env or environment
 *
 * Run selectively: vendor/bin/pest packages/agent-ctrl/tests/Integration/PiBridgeIntegrationTest.php
 */

function piIsAvailable(): bool
{
    return CliBinaryGuard::isAvailable('pi');
}

function piHasApiKey(): bool
{
    return !empty(Env::get('ANTHROPIC_API_KEY'))
        || !empty(Env::get('OPENAI_API_KEY'));
}

function skipIfPiUnavailable(): void
{
    if (!piIsAvailable()) {
        test()->markTestSkipped('pi binary not found in PATH');
    }
    if (!piHasApiKey()) {
        test()->markTestSkipped('No API key configured (ANTHROPIC_API_KEY or OPENAI_API_KEY)');
    }
}

it('executes a basic prompt via pi and returns response', function () {
    skipIfPiUnavailable();

    $response = AgentCtrl::pi()
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

    $response = AgentCtrl::pi()
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

    $response = AgentCtrl::pi()
        ->withTimeout(30)
        ->execute('Reply with "ok".');

    expect($response->sessionId())->not->toBeNull();
})->group('integration');

it('returns usage data from pi execution', function () {
    skipIfPiUnavailable();

    $response = AgentCtrl::pi()
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

    $response = AgentCtrl::pi()
        ->ephemeral()
        ->withTools(['read', 'grep', 'find', 'ls'])
        ->withTimeout(30)
        ->execute('Reply with "ok".');

    expect($response->isSuccess())->toBeTrue();
})->group('integration');

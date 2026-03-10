<?php declare(strict_types=1);

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;
use Cognesy\Config\Env;

/**
 * Integration smoke tests for Gemini CLI bridge.
 *
 * These tests require:
 * - `gemini` binary in PATH (npm install -g @google/gemini-cli or brew install gemini-cli)
 * - GEMINI_API_KEY or GOOGLE_API_KEY in monorepo root .env or environment, or Google account auth
 *
 * Run selectively: vendor/bin/pest packages/agent-ctrl/tests/Integration/GeminiBridgeIntegrationTest.php
 */

function geminiIsAvailable(): bool
{
    return CliBinaryGuard::isAvailable('gemini');
}

function geminiHasAuth(): bool
{
    return !empty(Env::get('GEMINI_API_KEY'))
        || !empty(Env::get('GOOGLE_API_KEY'));
}

function skipIfGeminiUnavailable(): void
{
    if (!geminiIsAvailable()) {
        test()->markTestSkipped('gemini binary not found in PATH');
    }
    if (!geminiHasAuth()) {
        test()->markTestSkipped('No API key configured (GEMINI_API_KEY or GOOGLE_API_KEY)');
    }
}

it('executes a basic prompt via gemini and returns response', function () {
    skipIfGeminiUnavailable();

    $response = AgentCtrl::gemini()
        ->withModel('flash')
        ->withApprovalMode(ApprovalMode::Plan)
        ->withTimeout(60)
        ->execute('Reply with exactly the word "pong" and nothing else.');

    expect($response)->toBeInstanceOf(AgentResponse::class)
        ->and($response->agentType)->toBe(AgentType::Gemini)
        ->and($response->exitCode)->toBe(0)
        ->and($response->isSuccess())->toBeTrue()
        ->and($response->text())->not->toBeEmpty()
        ->and(strtolower(trim($response->text())))->toContain('pong');
})->group('integration');

it('streams text via gemini callbacks', function () {
    skipIfGeminiUnavailable();

    $textChunks = [];

    $response = AgentCtrl::gemini()
        ->withModel('flash')
        ->withApprovalMode(ApprovalMode::Plan)
        ->withTimeout(60)
        ->onText(function (string $text) use (&$textChunks) {
            $textChunks[] = $text;
        })
        ->executeStreaming('Reply with exactly the word "hello" and nothing else.');

    expect($response->isSuccess())->toBeTrue()
        ->and($textChunks)->not->toBeEmpty()
        ->and(implode('', $textChunks))->not->toBeEmpty();
})->group('integration');

it('returns session id from gemini execution', function () {
    skipIfGeminiUnavailable();

    $response = AgentCtrl::gemini()
        ->withModel('flash')
        ->withApprovalMode(ApprovalMode::Plan)
        ->withTimeout(60)
        ->execute('Reply with "ok".');

    expect($response->sessionId())->not->toBeNull();
})->group('integration');

it('returns usage data from gemini execution', function () {
    skipIfGeminiUnavailable();

    $response = AgentCtrl::gemini()
        ->withModel('flash')
        ->withApprovalMode(ApprovalMode::Plan)
        ->withTimeout(60)
        ->execute('Reply with "ok".');

    $usage = $response->usage();
    expect($usage)->not->toBeNull()
        ->and($usage->input)->toBeGreaterThan(0)
        ->and($usage->output)->toBeGreaterThan(0);
})->group('integration');

it('uses plan mode for read-only analysis', function () {
    skipIfGeminiUnavailable();

    $response = AgentCtrl::gemini()
        ->withModel('flash')
        ->planMode()
        ->withTimeout(60)
        ->execute('Reply with "ok".');

    expect($response->isSuccess())->toBeTrue();
})->group('integration');

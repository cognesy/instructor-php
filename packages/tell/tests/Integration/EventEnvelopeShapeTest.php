<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Observability\TellEventNormalizer;

/**
 * The envelope is a wire format: `--output events` writes it to stdout, traces
 * write it to disk, and the agent protocol sends it between processes. Its keys
 * and their order are the contract, so they are pinned here rather than left to
 * whatever an array literal happens to emit.
 */
const TELL_ENVELOPE_KEYS = [
    'schema',
    'kind',
    'sequence',
    'executionId',
    'branch',
    'session',
    'timestamp',
    'metadata',
    'terminal',
];

it('emits the normalized envelope keys in a fixed order', function (): void {
    $now = new DateTimeImmutable();
    $normalizer = new TellEventNormalizer(branch: 'main', session: 'session-1');

    $envelope = $normalizer->normalize(new ToolCallCompleted(
        agentId: 'agent',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        tool: 'read',
        success: true,
        error: null,
        startedAt: $now,
        completedAt: $now,
    ));

    expect(array_keys($envelope))->toBe(TELL_ENVELOPE_KEYS)
        ->and($envelope['schema'])->toBe('tell.event.v1')
        ->and($envelope['kind'])->toBe('tool.completed')
        ->and($envelope['sequence'])->toBe(1)
        ->and($envelope['terminal'])->toBeNull();
});

it('numbers events from one and marks exactly the terminal one', function (): void {
    $now = new DateTimeImmutable();
    $normalizer = new TellEventNormalizer();

    $first = $normalizer->normalize(new ToolCallCompleted(
        agentId: 'agent',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        tool: 'read',
        success: true,
        error: null,
        startedAt: $now,
        completedAt: $now,
    ));
    $last = $normalizer->normalize(new AgentExecutionCompleted(
        agentId: 'agent',
        executionId: 'exec-1',
        parentAgentId: null,
        status: ExecutionStatus::Completed,
        totalSteps: 1,
        totalUsage: new InferenceUsage(),
        errors: null,
    ));

    expect($first['sequence'])->toBe(1)
        ->and($last['sequence'])->toBe(2)
        ->and($first['terminal'])->toBeNull()
        ->and($last['terminal'])->toBe('completed');
});

it('carries invocation context only on the boundary projection', function (): void {
    $now = new DateTimeImmutable();
    $normalized = (new TellEventNormalizer())->normalize(new ToolCallCompleted(
        agentId: 'agent',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        tool: 'read',
        success: true,
        error: null,
        startedAt: $now,
        completedAt: $now,
    ));

    $envelope = TellEventEnvelope::fromNormalized($normalized, TellExecutionMode::Durable, 'default');

    // The renderers and traces write the nine normalized keys; the SDK
    // projection appends the two the runtime knows and the normalizer does not.
    expect(array_keys($envelope->toArray()))->toBe([...TELL_ENVELOPE_KEYS, 'mode', 'agent'])
        ->and($envelope->mode)->toBe(TellExecutionMode::Durable)
        ->and($envelope->agent)->toBe('default');
});

it('keeps every metadata value a scalar, whatever the source event carried', function (): void {
    $now = new DateTimeImmutable();
    $normalizer = new TellEventNormalizer();

    $envelope = $normalizer->normalize(new ToolCallCompleted(
        agentId: 'agent',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        tool: 'read',
        success: true,
        error: null,
        startedAt: $now,
        completedAt: $now,
        result: ['secret' => 'raw tool payload canary'],
    ));

    foreach ($envelope['metadata'] as $value) {
        expect(is_scalar($value) || $value === null)->toBeTrue();
    }
    expect(json_encode($envelope, JSON_THROW_ON_ERROR))->not->toContain('raw tool payload canary');
});
